# Deployment Guide

This document describes how to deploy the Attendance System as separated services with secure internal networking, data protection controls, operational monitoring, and extensibility guidance.

## Service separation and topology

Deploy the system as distinct services with explicit network boundaries:

- **PHP web application** (`php-app/`): Handles UI, authentication, attendance workflows, and orchestration of AI requests.
- **Python AI service** (`python-ai/`): Performs face recognition and returns structured results.
- **Shared contracts** (`shared/`): Versioned JSON API contracts and DTOs referenced by both services.
- **Data services** (external dependencies): Database, object storage for images, cache/queue, and secrets manager.

A typical topology is:

```
[Client] -> [WAF/Ingress] -> [PHP App]
                           -> [Database]
                           -> [Object Storage]
                           -> [Queue]

[PHP App] -> (internal network) -> [Python AI Service]
```

## Internal network policies

Use a private network or service mesh for east-west traffic between the PHP app and Python AI service.

- **Private DNS + internal load balancer** for the AI service.
- **Mutual TLS (mTLS)** or TLS with service tokens for all inter-service calls.
- **Least-privilege service identities** for PHP, AI, and data services.
- **No direct public ingress** to the AI service; restrict it to internal CIDRs only.

## Firewall rules

Harden the perimeter with explicit allow rules and deny-by-default:

- **Ingress**
  - Allow HTTPS (443) to the PHP app from approved public sources (or the WAF).
  - Deny all public traffic to the AI service.
- **Egress**
  - Allow the PHP app to reach the AI service over its internal port (e.g., 8000).
  - Allow the PHP app to reach database, object storage, and queue endpoints.
  - Allow the AI service to reach model storage or registry endpoints if required.
- **East-West**
  - Restrict AI service traffic to requests from the PHP app identity only.

## Data-at-rest encryption

Protect data at rest across all persistent storage:

- **Database**: Enable built-in encryption (e.g., TDE) or volume-level encryption (KMS-managed keys).
- **Object storage**: Enable server-side encryption and bucket policies enforcing encryption headers.
- **Queue and cache**: Enable encryption for any persisted queue data or snapshots.
- **Secrets**: Store tokens and credentials in a secrets manager; do not place in source control.

## Backup and recovery procedures

Establish predictable backup routines and verify restore paths:

- **Database**
  - Nightly full backups, plus transaction log backups every 5-15 minutes.
  - Retain backups according to compliance requirements (e.g., 30-90 days).
  - Perform quarterly restore drills to a staging environment.
- **Object storage**
  - Enable bucket versioning and lifecycle policies for retention.
  - Replicate to a secondary region if required.
- **Configuration**
  - Back up infrastructure-as-code state files and secrets manager metadata.

## Monitoring and logging

Implement centralized observability across all services:

- **Metrics**
  - Request latency, error rates, queue depth, and AI inference time.
  - Database connection pool saturation and storage utilization.
- **Logs**
  - Structured JSON logs from PHP and AI services.
  - Correlation IDs propagated from the PHP app to the AI service.
  - Audit logs for admin actions and configuration changes.
- **Alerts**
  - AI service unavailability, high error rates, queue backlogs, and backup failures.

## Fallback behavior when AI service is unavailable

When the AI service is down or degraded, the PHP app should queue recognition work instead of failing the user flow:

- **Queueing**: Persist the request (image reference + metadata) to a durable queue.
- **Degraded UI**: Notify operators or users that recognition is delayed; allow manual review if required.
- **Retry policy**: Use exponential backoff with jitter, with a dead-letter queue for repeated failures.
- **Reconciliation**: Once the AI service recovers, workers drain the queue and update attendance records.

## Extensibility notes

Use these guidelines to extend the platform without breaking existing clients:

- **Org types**
  - Add new org types in the PHP domain model and persist them via migrations.
  - Update validation logic and any role-based policies tied to org type.
  - Expose new types in the admin UI and API outputs.
- **AI models**
  - Version AI model configuration per environment.
  - Add new models behind feature flags so traffic can be canary-tested.
  - Update `shared/api/` contract versions if request/response shapes change.
- **Integrations**
  - Introduce new external integrations behind a dedicated service or adapter layer.
  - Store integration credentials in the secrets manager and rotate regularly.
  - Document new webhooks or outbound callbacks in shared contracts if required.
