<?php

declare(strict_types=1);

namespace AttendanceSystem\Services;

use InvalidArgumentException;

final class FaceAIClient
{
    private const ALLOWED_IMAGE_TYPES = ['image/jpeg', 'image/png'];

    private string $baseUrl;
    private string $authKey;
    private string $authSecret;
    private int $timeoutSeconds;
    private $multiHandle;
    private array $pending = [];

    public function __construct(string $baseUrl, string $authKey, string $authSecret, int $timeoutSeconds = 10)
    {
        $this->baseUrl = rtrim($baseUrl, '/');
        $this->authKey = $authKey;
        $this->authSecret = $authSecret;
        $this->timeoutSeconds = $timeoutSeconds;
        $this->multiHandle = curl_multi_init();
    }

    public function __destruct()
    {
        if ($this->multiHandle instanceof \CurlMultiHandle) {
            curl_multi_close($this->multiHandle);
        }
    }

    public function queueLowRiskValidation(array $payload): string
    {
        $this->assertLowRiskPayload($payload);

        $requestId = $payload['request_id'];
        $path = '/v1/enroll-validate';
        $body = json_encode($payload, JSON_THROW_ON_ERROR);
        $timestamp = (string) time();
        $nonce = bin2hex(random_bytes(16));
        $signature = $this->signature('POST', $path, $timestamp, $nonce, $body);

        $ch = curl_init($this->baseUrl . $path);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $body,
            CURLOPT_TIMEOUT => $this->timeoutSeconds,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Accept: application/json',
                'X-Auth-Key: ' . $this->authKey,
                'X-Auth-Timestamp: ' . $timestamp,
                'X-Auth-Nonce: ' . $nonce,
                'X-Auth-Signature: ' . $signature,
            ],
        ]);

        curl_multi_add_handle($this->multiHandle, $ch);
        $this->pending[(int) $ch] = [
            'handle' => $ch,
            'request_id' => $requestId,
        ];

        return $requestId;
    }

    /**
     * @return array<int, array{request_id: string, response: mixed, http_code: int}>
     */
    public function drainResponses(): array
    {
        $responses = [];
        do {
            $status = curl_multi_exec($this->multiHandle, $running);
        } while ($status === CURLM_CALL_MULTI_PERFORM);

        if ($running) {
            curl_multi_select($this->multiHandle, 0.2);
        }

        while ($info = curl_multi_info_read($this->multiHandle)) {
            $handle = $info['handle'];
            $key = (int) $handle;
            $raw = curl_multi_getcontent($handle);
            $httpCode = (int) curl_getinfo($handle, CURLINFO_HTTP_CODE);
            $decoded = null;
            if ($raw !== false && $raw !== '') {
                $decoded = json_decode($raw, true);
            }
            $responses[] = [
                'request_id' => $this->pending[$key]['request_id'] ?? 'unknown',
                'response' => $decoded,
                'http_code' => $httpCode,
            ];

            curl_multi_remove_handle($this->multiHandle, $handle);
            curl_close($handle);
            unset($this->pending[$key]);
        }

        return $responses;
    }

    private function signature(string $method, string $path, string $timestamp, string $nonce, string $body): string
    {
        $hash = hash('sha256', $body);
        $canonical = strtoupper($method) . "\n" . $path . "\n" . $timestamp . "\n" . $nonce . "\n" . $hash;

        return hash_hmac('sha256', $canonical, $this->authSecret);
    }

    private function assertLowRiskPayload(array $payload): void
    {
        foreach (['request_id', 'person_id', 'image', 'risk_level'] as $field) {
            if (!array_key_exists($field, $payload)) {
                throw new InvalidArgumentException('Missing field: ' . $field);
            }
        }

        if ($payload['risk_level'] !== 'low') {
            throw new InvalidArgumentException('Only low risk validation is permitted.');
        }

        if (!is_array($payload['image'])) {
            throw new InvalidArgumentException('Invalid image payload.');
        }

        $image = $payload['image'];
        if (!isset($image['content_type'], $image['data_base64'])) {
            throw new InvalidArgumentException('Invalid image payload.');
        }

        if (!in_array($image['content_type'], self::ALLOWED_IMAGE_TYPES, true)) {
            throw new InvalidArgumentException('Unsupported image type.');
        }
    }
}
