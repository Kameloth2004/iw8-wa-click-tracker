<?php

declare(strict_types=1);

namespace IW8\WA\Http;

if (!defined('ABSPATH')) {
    exit;
}

final class ErrorFactory
{
    public static function make(string $code, string $message, int $status, array $details = [], ?int $retryAfter = null): \WP_REST_Response
    {
        $payload = [
            'error'      => $code,
            'message'    => $message,
            'status'     => $status,
            'request_id' => function_exists('wp_generate_uuid4') ? \wp_generate_uuid4() : uniqid('iw8_', true),
        ];
        if (!empty($details)) {
            $payload['details'] = $details;
        }
        if ($retryAfter !== null) {
            $payload['retry_after_seconds'] = $retryAfter;
        }
        return new \WP_REST_Response($payload, $status);
    }
}
