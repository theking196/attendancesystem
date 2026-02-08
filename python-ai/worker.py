#!/usr/bin/env python3
from __future__ import annotations

import importlib
import json
import os
import time
from typing import Any, Optional

from psycopg.rows import dict_row

psycopg = importlib.import_module("psycopg")


def connect():
    dsn = os.environ.get("DB_DSN")
    if not dsn:
        raise RuntimeError("DB_DSN environment variable is required.")

    user = os.environ.get("DB_USER")
    password = os.environ.get("DB_PASSWORD")

    return psycopg.connect(dsn, user=user, password=password)


def claim_job(conn) -> Optional[dict[str, Any]]:
    with conn.transaction():
        with conn.cursor(row_factory=dict_row) as cursor:
            cursor.execute(
                """
                SELECT id, payload
                FROM jobs
                WHERE status = 'queued'
                  AND job_type = 'attendance_recognition'
                  AND (run_at IS NULL OR run_at <= NOW())
                ORDER BY created_at
                FOR UPDATE SKIP LOCKED
                LIMIT 1
                """
            )
            job = cursor.fetchone()
            if job is None:
                return None

            cursor.execute(
                "UPDATE jobs SET status = 'processing', updated_at = NOW() WHERE id = %s",
                (job["id"],),
            )

            return job


def complete_job(conn, job_id: int, status: str) -> None:
    with conn.transaction():
        with conn.cursor() as cursor:
            cursor.execute(
                "UPDATE jobs SET status = %s, updated_at = NOW() WHERE id = %s",
                (status, job_id),
            )


def process_payload(payload: Any) -> None:
    print("Processing payload:", payload, flush=True)
    time.sleep(1)


def main() -> None:
    poll_interval = float(os.environ.get("JOB_POLL_INTERVAL", "5"))

    with connect() as conn:
        while True:
            job = claim_job(conn)
            if job is None:
                time.sleep(poll_interval)
                continue

            job_id = job["id"]
            payload = job["payload"]
            if isinstance(payload, str):
                try:
                    payload = json.loads(payload)
                except json.JSONDecodeError:
                    pass

            try:
                process_payload(payload)
            except Exception:
                complete_job(conn, job_id, "failed")
                raise
            else:
                complete_job(conn, job_id, "completed")


if __name__ == "__main__":
    main()
