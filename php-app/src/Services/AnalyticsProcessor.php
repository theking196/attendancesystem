<?php

declare(strict_types=1);

namespace AttendanceSystem\Services;

use AttendanceSystem\Database;
use DateTimeImmutable;

final class AnalyticsProcessor
{
    private const ATTENDED_STATUSES = ['present', 'late'];

    public function processDaily(DateTimeImmutable $day): void
    {
        $periodStart = $day->format('Y-m-d');
        $periodEnd = $day->modify('+1 day')->format('Y-m-d');

        Database::execute(
            'DELETE FROM analytics_daily WHERE period_start = :period_start',
            ['period_start' => $periodStart]
        );

        $metrics = $this->calculateAggregateMetrics($periodStart, $periodEnd);
        foreach ($metrics as $metricKey => $metricValue) {
            Database::insert('analytics_daily', [
                'period_start' => $periodStart,
                'period_end' => $periodEnd,
                'metric_key' => $metricKey,
                'metric_value' => $metricValue,
            ]);
        }
    }

    public function processMonthly(DateTimeImmutable $monthStart): void
    {
        $periodStart = $monthStart->format('Y-m-d');
        $periodEnd = $monthStart->modify('first day of next month')->format('Y-m-d');

        Database::execute(
            'DELETE FROM analytics_monthly WHERE period_start = :period_start',
            ['period_start' => $periodStart]
        );

        $metrics = $this->calculateAggregateMetrics($periodStart, $periodEnd);
        foreach ($metrics as $metricKey => $metricValue) {
            Database::insert('analytics_monthly', [
                'period_start' => $periodStart,
                'period_end' => $periodEnd,
                'metric_key' => $metricKey,
                'metric_value' => $metricValue,
            ]);
        }
    }

    public function processEngagementScores(DateTimeImmutable $periodStart): void
    {
        $start = $periodStart->format('Y-m-d');
        $end = $periodStart->modify('first day of next month')->format('Y-m-d');

        Database::execute(
            'DELETE FROM engagement_scores WHERE period_start = :period_start',
            ['period_start' => $start]
        );

        $rows = Database::select(
            'SELECT user_id,
                COUNT(*) AS total_count,
                SUM(CASE WHEN status IN (\'present\', \'late\') THEN 1 ELSE 0 END) AS attended_count
            FROM attendance_logs
            WHERE attended_at >= :start AND attended_at < :end
            GROUP BY user_id',
            ['start' => $start, 'end' => $end]
        );

        foreach ($rows as $row) {
            $totalCount = (int) $row['total_count'];
            $attendedCount = (int) $row['attended_count'];
            $score = $totalCount > 0 ? round(($attendedCount / $totalCount) * 100, 2) : 0.0;

            Database::insert('engagement_scores', [
                'user_id' => (int) $row['user_id'],
                'period_start' => $start,
                'period_end' => $end,
                'score' => $score,
                'attended_count' => $attendedCount,
                'total_count' => $totalCount,
            ]);
        }
    }

    public function detectAnomalies(DateTimeImmutable $anchorDay): void
    {
        $this->detectFrequentLateness($anchorDay);
        $this->detectDecliningParticipation($anchorDay);
    }

    private function calculateAggregateMetrics(string $periodStart, string $periodEnd): array
    {
        $rows = Database::select(
            'SELECT
                COUNT(*) AS total_logs,
                COUNT(DISTINCT user_id) AS unique_users,
                SUM(CASE WHEN status = \'present\' THEN 1 ELSE 0 END) AS present_count,
                SUM(CASE WHEN status = \'late\' THEN 1 ELSE 0 END) AS late_count,
                SUM(CASE WHEN status = \'absent\' THEN 1 ELSE 0 END) AS absent_count
            FROM attendance_logs
            WHERE attended_at >= :start AND attended_at < :end',
            ['start' => $periodStart, 'end' => $periodEnd]
        );

        $metrics = $rows[0] ?? [];

        return [
            'total_logs' => (float) ($metrics['total_logs'] ?? 0),
            'unique_users' => (float) ($metrics['unique_users'] ?? 0),
            'present_count' => (float) ($metrics['present_count'] ?? 0),
            'late_count' => (float) ($metrics['late_count'] ?? 0),
            'absent_count' => (float) ($metrics['absent_count'] ?? 0),
        ];
    }

    private function detectFrequentLateness(DateTimeImmutable $anchorDay): void
    {
        $periodEnd = $anchorDay->format('Y-m-d');
        $periodStart = $anchorDay->modify('-14 days')->format('Y-m-d');

        Database::execute(
            'DELETE FROM analytics_alerts WHERE alert_type = :alert_type AND period_start = :period_start',
            ['alert_type' => 'frequent_lateness', 'period_start' => $periodStart]
        );

        $rows = Database::select(
            'SELECT user_id, COUNT(*) AS late_count
            FROM attendance_logs
            WHERE status = :status AND attended_at >= :start AND attended_at < :end
            GROUP BY user_id
            HAVING COUNT(*) >= :threshold',
            [
                'status' => 'late',
                'start' => $periodStart,
                'end' => $periodEnd,
                'threshold' => 3,
            ]
        );

        foreach ($rows as $row) {
            $details = json_encode([
                'late_count' => (int) $row['late_count'],
                'window_days' => 14,
            ], JSON_THROW_ON_ERROR);

            Database::insert('analytics_alerts', [
                'user_id' => (int) $row['user_id'],
                'alert_type' => 'frequent_lateness',
                'severity' => 'medium',
                'period_start' => $periodStart,
                'period_end' => $periodEnd,
                'details' => $details,
            ]);
        }
    }

    private function detectDecliningParticipation(DateTimeImmutable $anchorDay): void
    {
        $currentEnd = $anchorDay->format('Y-m-d');
        $currentStart = $anchorDay->modify('-30 days')->format('Y-m-d');
        $previousStart = $anchorDay->modify('-60 days')->format('Y-m-d');
        $previousEnd = $currentStart;

        Database::execute(
            'DELETE FROM analytics_alerts WHERE alert_type = :alert_type AND period_start = :period_start',
            ['alert_type' => 'declining_participation', 'period_start' => $currentStart]
        );

        $previousRates = $this->fetchParticipationRates($previousStart, $previousEnd);
        $currentRates = $this->fetchParticipationRates($currentStart, $currentEnd);

        foreach ($currentRates as $userId => $current) {
            if (!array_key_exists($userId, $previousRates)) {
                continue;
            }

            $previous = $previousRates[$userId];
            if ($previous['total_count'] === 0 || $current['total_count'] === 0) {
                continue;
            }

            $previousRate = $previous['attended_count'] / $previous['total_count'];
            $currentRate = $current['attended_count'] / $current['total_count'];
            $drop = $previousRate - $currentRate;

            if ($drop < 0.20) {
                continue;
            }

            $details = json_encode([
                'previous_rate' => round($previousRate * 100, 2),
                'current_rate' => round($currentRate * 100, 2),
                'drop_points' => round($drop * 100, 2),
                'window_days' => 30,
            ], JSON_THROW_ON_ERROR);

            Database::insert('analytics_alerts', [
                'user_id' => $userId,
                'alert_type' => 'declining_participation',
                'severity' => 'high',
                'period_start' => $currentStart,
                'period_end' => $currentEnd,
                'details' => $details,
            ]);
        }
    }

    private function fetchParticipationRates(string $start, string $end): array
    {
        $rows = Database::select(
            'SELECT user_id,
                COUNT(*) AS total_count,
                SUM(CASE WHEN status IN (\'present\', \'late\') THEN 1 ELSE 0 END) AS attended_count
            FROM attendance_logs
            WHERE attended_at >= :start AND attended_at < :end
            GROUP BY user_id',
            ['start' => $start, 'end' => $end]
        );

        $rates = [];
        foreach ($rows as $row) {
            $userId = (int) $row['user_id'];
            $rates[$userId] = [
                'total_count' => (int) $row['total_count'],
                'attended_count' => (int) $row['attended_count'],
            ];
        }

        return $rates;
    }
}
