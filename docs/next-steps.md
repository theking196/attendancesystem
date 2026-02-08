# Codebase check & next steps

## Findings
- The repository now includes a baseline hybrid structure: `php-app/`, `python-ai/`, and `shared/` with supporting documentation under `docs/`.
- Core documents for the data model, analytics worker, and deployment guidance already exist.
- The next phase is to validate implementation coverage in the PHP and Python services, and fill in any missing runtime and integration details.

## Recommended next steps
1. **Review PHP orchestrator implementation**
   - Verify tenant context binding and RBAC guards in `php-app/bootstrap.php` and core middleware.
   - Confirm session handling, token workflows, and data access helpers enforce organization scoping.
2. **Validate database migrations and schema alignment**
   - Ensure migrations implement the documented multi-tenant schema in `docs/schema.md`.
   - Confirm composite indexes on `(organization_id, attended_at)` and analytics period fields exist.
3. **Confirm internal API contract compliance**
   - Check PHP client and Python API endpoints against `shared/api/v1/face-recognition.json`.
   - Validate authentication headers and error handling with integration tests.
4. **Exercise the async job workflow**
   - Ensure attendance submissions enqueue jobs and return a job id immediately.
   - Validate Python worker processing, result callbacks, and job status updates.
5. **Wire analytics and notification jobs**
   - Run the analytics worker described in `docs/analytics_jobs.md` and verify rollups.
   - Confirm email templates, queue workers, and delivery status tracking behave as expected.
6. **Front-end readiness check**
   - Ensure the vanilla JS SPA modules load via the versioned API and respect RBAC.
   - Validate dashboards read from analytics tables and exports are tenant-scoped.
7. **Operational readiness**
   - Apply the deployment guidance from `docs/deployment.md`.
   - Configure monitoring, backups, and AI service fallback queues in the target environment.
