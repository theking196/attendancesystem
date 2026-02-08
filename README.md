# Attendance System Hybrid Architecture

This repository is organized as a hybrid system that separates the web-facing PHP application from the face-recognition service while sharing versioned API contracts and data transfer objects.

## Top-level layout

- `php-app/`: The vanilla PHP web application (UI + server-side orchestration).
- `python-ai/`: The face-recognition service (Python microservice).
- `shared/`: Versioned API schemas and shared DTOs used by both components.

## Internal JSON API boundaries

The PHP application communicates with the Python service over an internal JSON API. All requests and responses must conform to the versioned contract documents stored under `shared/api/`.

### Contract versioning

- Contracts are versioned by path (e.g., `shared/api/v1/face-recognition.json`).
- Breaking changes require a new version directory (e.g., `v2`).
- Non-breaking additions should update the current version document.

### High-level workflow

1. The PHP app captures or uploads a face image.
2. It sends a JSON request to the Python AI service using the current contract version.
3. The Python service responds with a structured payload including recognition results or errors.
4. The PHP app maps the response into its domain models and persists attendance data.

### Security boundary

- The internal API is authenticated using a shared service token.
- Transport security is required (TLS within the private network or service mesh).
- The contract specifies required headers and error codes for authentication failures.

See `shared/api/v1/face-recognition.json` for the detailed schema, error catalog, and security requirements.
