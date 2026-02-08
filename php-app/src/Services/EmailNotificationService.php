<?php

declare(strict_types=1);

namespace AttendanceSystem\Services;

use AttendanceSystem\Database;
use DateTimeImmutable;
use RuntimeException;

final class EmailNotificationService
{
    public const STATUS_QUEUED = 'queued';
    public const STATUS_PROCESSING = 'processing';
    public const STATUS_SENT = 'sent';
    public const STATUS_FAILED = 'failed';

    private const TRIGGER_PREFIX = 'email.trigger.';
    private const TEMPLATE_PREFIX = 'email.template.';

    private ConfigSettingsService $configSettings;
    private EmailTransport $transport;

    public function __construct(
        ?ConfigSettingsService $configSettings = null,
        ?EmailTransport $transport = null
    ) {
        $this->configSettings = $configSettings ?? new ConfigSettingsService();
        $this->transport = $transport ?? new EmailTransport();
    }

    public function enqueueTemplateEmail(
        string $templateKey,
        string $recipientEmail,
        array $templateData = [],
        ?DateTimeImmutable $scheduledAt = null
    ): ?int {
        if (!$this->isTriggerEnabled($templateKey)) {
            return null;
        }

        $template = $this->resolveTemplate($templateKey);
        $subject = $this->renderTemplate($template['subject'], $templateData);
        $body = $this->renderTemplate($template['body'], $templateData);

        $encodedData = json_encode($templateData, JSON_THROW_ON_ERROR);

        return Database::insert('email_queue', [
            'template_id' => $template['id'],
            'recipient_email' => $recipientEmail,
            'subject' => $subject,
            'body' => $body,
            'template_data' => $encodedData,
            'status' => self::STATUS_QUEUED,
            'scheduled_at' => $scheduledAt?->format('Y-m-d H:i:s'),
        ]);
    }

    public function sendQueuedEmails(int $limit = 10): int
    {
        $rows = Database::select(
            'SELECT id, recipient_email, subject, body, status
            FROM email_queue
            WHERE status = :status
                AND (scheduled_at IS NULL OR scheduled_at <= :now)
            ORDER BY created_at ASC
            LIMIT :limit',
            [
                'status' => self::STATUS_QUEUED,
                'now' => (new DateTimeImmutable())->format('Y-m-d H:i:s'),
                'limit' => $limit,
            ]
        );

        $processed = 0;

        foreach ($rows as $row) {
            $emailId = (int) $row['id'];
            $recipient = (string) $row['recipient_email'];
            $subject = (string) $row['subject'];
            $body = (string) $row['body'];

            $this->markProcessing($emailId);

            try {
                $this->transport->send($recipient, $subject, $body);
                $this->markSent($emailId);
            } catch (RuntimeException $exception) {
                $this->markFailed($emailId, $exception->getMessage());
            }

            $processed++;
        }

        return $processed;
    }

    private function resolveTemplate(string $templateKey): array
    {
        $rows = Database::select(
            'SELECT id, subject, body FROM email_templates WHERE template_key = :template_key',
            ['template_key' => $templateKey]
        );

        $template = $rows[0] ?? null;

        $subjectOverride = $this->configSettings->get(self::TEMPLATE_PREFIX . $templateKey . '.subject');
        $bodyOverride = $this->configSettings->get(self::TEMPLATE_PREFIX . $templateKey . '.body');

        $subject = $subjectOverride ?? ($template['subject'] ?? null);
        $body = $bodyOverride ?? ($template['body'] ?? null);

        if ($subject === null || $body === null) {
            throw new RuntimeException(sprintf('Email template "%s" is missing.', $templateKey));
        }

        return [
            'id' => $template['id'] ?? null,
            'subject' => $subject,
            'body' => $body,
        ];
    }

    private function isTriggerEnabled(string $templateKey): bool
    {
        return $this->configSettings->getBoolean(
            self::TRIGGER_PREFIX . $templateKey . '.enabled',
            true
        );
    }

    private function renderTemplate(string $template, array $data): string
    {
        $rendered = $template;

        foreach ($data as $key => $value) {
            $rendered = str_replace('{{' . $key . '}}', (string) $value, $rendered);
        }

        return $rendered;
    }

    private function markProcessing(int $emailId): void
    {
        Database::execute(
            'UPDATE email_queue
            SET status = :status,
                last_attempt_at = :last_attempt_at,
                attempt_count = attempt_count + 1
            WHERE id = :id',
            [
                'status' => self::STATUS_PROCESSING,
                'last_attempt_at' => (new DateTimeImmutable())->format('Y-m-d H:i:s'),
                'id' => $emailId,
            ]
        );
    }

    private function markSent(int $emailId): void
    {
        Database::execute(
            'UPDATE email_queue
            SET status = :status,
                sent_at = :sent_at,
                failure_reason = NULL
            WHERE id = :id',
            [
                'status' => self::STATUS_SENT,
                'sent_at' => (new DateTimeImmutable())->format('Y-m-d H:i:s'),
                'id' => $emailId,
            ]
        );
    }

    private function markFailed(int $emailId, string $reason): void
    {
        Database::execute(
            'UPDATE email_queue
            SET status = :status,
                failure_reason = :failure_reason
            WHERE id = :id',
            [
                'status' => self::STATUS_FAILED,
                'failure_reason' => $reason,
                'id' => $emailId,
            ]
        );
    }
}
