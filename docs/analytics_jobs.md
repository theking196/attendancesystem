# Analytics processing jobs

The analytics pipeline runs as a scheduled CLI worker that aggregates immutable `attendance_logs` into rollup tables and anomaly alerts. Configure your scheduler to execute the worker once per day for each organization.

## Cron example

```cron
# Run daily analytics at 01:15 for organization 42
15 1 * * * /usr/bin/php /path/to/php-app/bin/analytics_worker.php --org=42
```

## Inputs

- `--org` (required): organization id.
- `--date` (optional): day to roll up in `YYYY-MM-DD` format (defaults to today).
- `--month` (optional): month start in `YYYY-MM-DD` format (defaults to first day of this month).

## Outputs

The worker writes to:

- `analytics_daily` and `analytics_monthly` rollups.
- `engagement_scores` per user for the month.
- `analytics_alerts` for frequent lateness and declining participation.
