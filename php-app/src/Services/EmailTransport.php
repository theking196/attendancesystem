<?php

declare(strict_types=1);

namespace AttendanceSystem\Services;

use RuntimeException;

final class EmailTransport
{
    private string $mode;

    public function __construct(?string $mode = null)
    {
        $this->mode = $mode ?? (getenv('EMAIL_TRANSPORT') ?: 'log');
    }

    public function send(string $recipient, string $subject, string $body): void
    {
        if ($this->mode === 'noop') {
            return;
        }

        if ($this->mode === 'log') {
            error_log(sprintf(
                '[EmailTransport] To: %s | Subject: %s | Body: %s',
                $recipient,
                $subject,
                $body
            ));

            return;
        }

        if ($this->mode === 'mail') {
            $success = mail($recipient, $subject, $body);
            if (!$success) {
                throw new RuntimeException('mail() transport failed to send message.');
            }

            return;
        }

        throw new RuntimeException(sprintf('Unsupported email transport mode "%s".', $this->mode));
    }
}
