<?php

declare(strict_types=1);

namespace AttendanceSystem\Services;

use AttendanceSystem\Database;

final class AnalyticsService
{
    public function fetchDailyMetrics(string $start, string $end): array
    {
        return Database::select(
            'SELECT period_start, period_end, metric_key, metric_value
            FROM analytics_daily
            WHERE period_start >= :start AND period_start <= :end
            ORDER BY period_start, metric_key',
            ['start' => $start, 'end' => $end]
        );
    }

    public function fetchMonthlyMetrics(string $start, string $end): array
    {
        return Database::select(
            'SELECT period_start, period_end, metric_key, metric_value
            FROM analytics_monthly
            WHERE period_start >= :start AND period_start <= :end
            ORDER BY period_start, metric_key',
            ['start' => $start, 'end' => $end]
        );
    }

    public function fetchEngagementScores(string $start, string $end): array
    {
        return Database::select(
            'SELECT user_id, period_start, period_end, score, attended_count, total_count
            FROM engagement_scores
            WHERE period_start >= :start AND period_start <= :end
            ORDER BY period_start, score DESC',
            ['start' => $start, 'end' => $end]
        );
    }

    public function fetchAlerts(string $start, string $end): array
    {
        return Database::select(
            'SELECT user_id, alert_type, severity, period_start, period_end, details, created_at
            FROM analytics_alerts
            WHERE period_start >= :start AND period_start <= :end
            ORDER BY period_start DESC, severity DESC',
            ['start' => $start, 'end' => $end]
        );
    }
}
