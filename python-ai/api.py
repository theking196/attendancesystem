#!/usr/bin/env python3
from __future__ import annotations

import base64
import binascii
import hashlib
import hmac
import ipaddress
import json
import os
import time
import uuid
from http import HTTPStatus
from http.server import BaseHTTPRequestHandler, HTTPServer
from typing import Any

ALLOWED_IMAGE_TYPES = {"image/jpeg", "image/png"}

ERRORS = {
    "AUTH_MISSING_TOKEN": (HTTPStatus.UNAUTHORIZED, "Missing authentication token."),
    "AUTH_INVALID_TOKEN": (HTTPStatus.UNAUTHORIZED, "Authentication token is invalid."),
    "VALIDATION_FAILED": (HTTPStatus.BAD_REQUEST, "Request validation failed."),
    "IMAGE_DECODE_FAILED": (HTTPStatus.UNPROCESSABLE_ENTITY, "Image could not be decoded or is corrupted."),
    "FACE_NOT_FOUND": (HTTPStatus.UNPROCESSABLE_ENTITY, "No face detected in the image."),
    "INTERNAL_ERROR": (HTTPStatus.INTERNAL_SERVER_ERROR, "Unexpected service error."),
}


def _env_list(name: str) -> list[str]:
    value = os.environ.get(name, "")
    if not value:
        return []
    return [item.strip() for item in value.split(",") if item.strip()]


def _allowed_hosts() -> list[str]:
    hosts = _env_list("ALLOWED_BIND_HOSTS")
    return hosts or ["127.0.0.1"]


def _allowed_client_networks() -> list[ipaddress._BaseNetwork]:
    entries = _env_list("ALLOWED_CLIENT_IPS")
    if not entries:
        entries = ["127.0.0.1/32", "::1/128"]
    networks: list[ipaddress._BaseNetwork] = []
    for entry in entries:
        networks.append(ipaddress.ip_network(entry, strict=False))
    return networks


def _canonical_signature(method: str, path: str, timestamp: str, nonce: str, body: bytes) -> str:
    payload_hash = hashlib.sha256(body).hexdigest()
    parts = [method.upper(), path, timestamp, nonce, payload_hash]
    return "\n".join(parts)


def _error_payload(request_id: str, code: str, details: dict[str, Any] | None = None) -> dict[str, Any]:
    status, message = ERRORS[code]
    payload: dict[str, Any] = {
        "request_id": request_id,
        "code": code,
        "message": message,
    }
    if details:
        payload["details"] = details
    return payload


def _read_body(handler: BaseHTTPRequestHandler, max_bytes: int) -> bytes:
    length_header = handler.headers.get("Content-Length")
    if length_header is None:
        return b""
    try:
        length = int(length_header)
    except ValueError:
        length = 0
    if length > max_bytes:
        raise ValueError("Request body too large")
    return handler.rfile.read(length)


def _validate_image(image: Any) -> None:
    if not isinstance(image, dict):
        raise ValueError("Image payload must be an object")
    content_type = image.get("content_type")
    if content_type not in ALLOWED_IMAGE_TYPES:
        raise ValueError("Unsupported content_type")
    data = image.get("data_base64")
    if not isinstance(data, str) or not data:
        raise ValueError("Missing data_base64")
    try:
        base64.b64decode(data, validate=True)
    except (ValueError, binascii.Error):
        raise ValueError("Invalid base64")


def _extract_request_id(payload: dict[str, Any] | None) -> str:
    if payload and isinstance(payload.get("request_id"), str) and payload["request_id"]:
        return payload["request_id"]
    return str(uuid.uuid4())


class FaceAIHandler(BaseHTTPRequestHandler):
    server_version = "FaceAI/1.0"

    def _send_json(self, status: HTTPStatus, payload: dict[str, Any]) -> None:
        data = json.dumps(payload).encode("utf-8")
        self.send_response(status.value)
        self.send_header("Content-Type", "application/json")
        self.send_header("Content-Length", str(len(data)))
        self.end_headers()
        self.wfile.write(data)

    def _send_error(self, code: str, request_id: str, details: dict[str, Any] | None = None) -> None:
        status, _ = ERRORS[code]
        self._send_json(status, _error_payload(request_id, code, details))

    def _client_allowed(self) -> bool:
        client_ip = self.client_address[0]
        ip = ipaddress.ip_address(client_ip)
        for network in self.server.allowed_client_networks:
            if ip in network:
                return True
        return False

    def _authenticate(self, body: bytes) -> bool:
        key_id = self.headers.get("X-Auth-Key")
        signature = self.headers.get("X-Auth-Signature")
        timestamp = self.headers.get("X-Auth-Timestamp")
        nonce = self.headers.get("X-Auth-Nonce")
        if not key_id or not signature or not timestamp or not nonce:
            return False
        if key_id != self.server.auth_key:
            return False
        try:
            ts_int = int(timestamp)
        except ValueError:
            return False
        skew = abs(int(time.time()) - ts_int)
        if skew > self.server.timestamp_skew:
            return False
        canonical = _canonical_signature(self.command, self.path, timestamp, nonce, body)
        expected = hmac.new(self.server.auth_secret, canonical.encode("utf-8"), hashlib.sha256).hexdigest()
        return hmac.compare_digest(expected, signature)

    def _handle_request(self) -> None:
        if not self._client_allowed():
            request_id = str(uuid.uuid4())
            self._send_error("AUTH_INVALID_TOKEN", request_id, {"reason": "client_not_allowed"})
            return

        max_body = self.server.max_body_bytes
        try:
            body = _read_body(self, max_body)
        except ValueError:
            request_id = str(uuid.uuid4())
            self._send_error("VALIDATION_FAILED", request_id, {"reason": "body_too_large"})
            return

        if not self._authenticate(body):
            request_id = str(uuid.uuid4())
            if any(
                header in self.headers
                for header in ("X-Auth-Key", "X-Auth-Signature", "X-Auth-Timestamp", "X-Auth-Nonce")
            ):
                self._send_error("AUTH_INVALID_TOKEN", request_id)
            else:
                self._send_error("AUTH_MISSING_TOKEN", request_id)
            return

        if self.headers.get("Content-Type") != "application/json":
            request_id = str(uuid.uuid4())
            self._send_error("VALIDATION_FAILED", request_id, {"reason": "content_type"})
            return

        try:
            payload = json.loads(body.decode("utf-8")) if body else {}
        except json.JSONDecodeError:
            request_id = str(uuid.uuid4())
            self._send_error("VALIDATION_FAILED", request_id, {"reason": "invalid_json"})
            return

        if self.path == "/v1/recognize":
            self._handle_recognize(payload)
        elif self.path == "/v1/enroll-validate":
            self._handle_enroll_validate(payload)
        else:
            self.send_response(HTTPStatus.NOT_FOUND.value)
            self.end_headers()

    def _handle_recognize(self, payload: dict[str, Any]) -> None:
        request_id = _extract_request_id(payload)
        if "image" not in payload:
            self._send_error("VALIDATION_FAILED", request_id, {"field": "image"})
            return
        try:
            _validate_image(payload["image"])
        except ValueError as exc:
            self._send_error("IMAGE_DECODE_FAILED", request_id, {"error": str(exc)})
            return

        response = {
            "request_id": request_id,
            "status": "unrecognized",
            "faces": [],
            "processing_time_ms": 5,
        }
        self._send_json(HTTPStatus.OK, response)

    def _handle_enroll_validate(self, payload: dict[str, Any]) -> None:
        request_id = _extract_request_id(payload)
        required_fields = ["person_id", "image", "risk_level"]
        missing = [field for field in required_fields if field not in payload]
        if missing:
            self._send_error("VALIDATION_FAILED", request_id, {"fields": missing})
            return
        if payload.get("risk_level") != "low":
            self._send_error("VALIDATION_FAILED", request_id, {"field": "risk_level"})
            return
        try:
            _validate_image(payload["image"])
        except ValueError as exc:
            self._send_error("IMAGE_DECODE_FAILED", request_id, {"error": str(exc)})
            return

        response = {
            "request_id": request_id,
            "status": "accepted",
            "validation": {
                "person_id": payload.get("person_id"),
                "matches": False,
                "confidence": 0.0,
            },
            "processing_time_ms": 5,
        }
        self._send_json(HTTPStatus.OK, response)

    def do_POST(self) -> None:  # noqa: N802
        try:
            self._handle_request()
        except Exception:
            request_id = str(uuid.uuid4())
            self._send_error("INTERNAL_ERROR", request_id)

    def log_message(self, format: str, *args: Any) -> None:
        if self.server.enable_access_log:
            super().log_message(format, *args)


class FaceAIServer(HTTPServer):
    def __init__(self, server_address: tuple[str, int], handler: type[BaseHTTPRequestHandler]) -> None:
        super().__init__(server_address, handler)
        self.auth_key = os.environ.get("AI_AUTH_KEY", "")
        secret = os.environ.get("AI_AUTH_SECRET", "")
        if not self.auth_key or not secret:
            raise RuntimeError("AI_AUTH_KEY and AI_AUTH_SECRET are required")
        self.auth_secret = secret.encode("utf-8")
        self.allowed_client_networks = _allowed_client_networks()
        self.max_body_bytes = int(os.environ.get("MAX_BODY_BYTES", str(5 * 1024 * 1024)))
        self.timestamp_skew = int(os.environ.get("AUTH_TIMESTAMP_SKEW", "300"))
        self.enable_access_log = os.environ.get("ACCESS_LOG", "false").lower() == "true"


def main() -> None:
    bind_host = os.environ.get("BIND_HOST", "127.0.0.1")
    bind_port = int(os.environ.get("BIND_PORT", "8000"))
    allowed_hosts = _allowed_hosts()
    if bind_host not in allowed_hosts:
        raise RuntimeError(f"BIND_HOST {bind_host} not in ALLOWED_BIND_HOSTS")

    server = FaceAIServer((bind_host, bind_port), FaceAIHandler)
    print(f"FaceAI API listening on http://{bind_host}:{bind_port}")
    server.serve_forever()


if __name__ == "__main__":
    main()
