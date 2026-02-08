# Attendance System Data Model

## Core tenancy
- `organizations` is the root table for tenancy. All tenant-owned tables reference `organizations.id` via a required `organization_id` foreign key with `ON DELETE CASCADE` to ensure tenant data is removed with the organization.

## Tenant-owned tables and relationships
- `roles` belong to an organization and are unique per organization by name.
- `users` belong to an organization, optionally reference a role, and enforce unique email addresses within an organization.
- `attendance_logs` belong to an organization and user, capturing attendance moments per tenant.
- `analytics_daily`, `analytics_weekly`, and `analytics_monthly` store rollups keyed by period windows for each organization.
- `config_settings` store per-tenant configuration entries keyed by `setting_key`.
- `facial_embeddings` store per-user biometric embeddings tied to an organization.
- `jobs` store background work per tenant with JSON payloads.
- `email_templates` store reusable templates per organization.
- `email_queue` stores outbound email requests and optionally references an email template.

## Indexing rationale
- `attendance_logs` uses a composite index on `(organization_id, attended_at)` to speed up tenant-scoped attendance queries by time range.
- Each analytics table uses a composite index on `(organization_id, period_start)` to accelerate tenant-specific period aggregations and trend reads.
- Uniqueness constraints for `roles`, `users`, `config_settings`, and `email_templates` are scoped to `(organization_id, key)` so tenant data remains isolated while allowing duplicate values across organizations.
