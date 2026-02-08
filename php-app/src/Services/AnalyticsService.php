<?php

declare(strict_types=1);

namespace AttendanceSystem\Services;

use AttendanceSystem\Database;

final class AnalyticsService
{
    public function fetchDailyMetrics(string $start, string $end): array
    {
        $rows = Database::select(
            'SELECT period_start, period_end, metric_key, metric_value
            FROM analytics_daily
            WHERE period_start >= :start AND period_start <= :end
            ORDER BY period_start, metric_key',
            ['start' => $start, 'end' => $end]
        );

        return $this->pivotMetrics($rows);
    }

    public function fetchMonthlyMetrics(string $start, string $end): array
    {
        $rows = Database::select(
            'SELECT period_start, period_end, metric_key, metric_value
            FROM analytics_monthly
            WHERE period_start >= :start AND period_start <= :end
            ORDER BY period_start, metric_key',
            ['start' => $start, 'end' => $end]
        );

        return $this->pivotMetrics($rows);
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
        $rows = Database::select(
            'SELECT user_id, alert_type, severity, period_start, period_end, details, created_at
            FROM analytics_alerts
            WHERE period_start >= :start AND period_start <= :end
            ORDER BY period_start DESC, severity DESC',
            ['start' => $start, 'end' => $end]
        );

        return array_map(static function (array $row): array {
            $details = $row['details'];
            if (is_string($details)) {
                $decoded = json_decode($details, true);
                if ($decoded !== null || json_last_error() === JSON_ERROR_NONE) {
                    $details = $decoded;
                }
            }

            return [
                'user_id' => $row['user_id'],
                'alert_type' => $row['alert_type'],
                'severity' => $row['severity'],
                'period_start' => $row['period_start'],
                'period_end' => $row['period_end'],
                'details' => $details,
                'created_at' => $row['created_at'],
            ];
        }, $rows);
    }

    private function pivotMetrics(array $rows): array
    {
        $grouped = [];

        foreach ($rows as $row) {
            $periodStart = $row['period_start'];
            if (!isset($grouped[$periodStart])) {
                $grouped[$periodStart] = [
                    'period_start' => $periodStart,
                    'period_end' => $row['period_end'],
                    'metrics' => [],
                ];
            }

            $metricKey = $row['metric_key'];
            $grouped[$periodStart]['metrics'][$metricKey] = (float) $row['metric_value'];
        }

        return array_values($grouped);
    }
}
