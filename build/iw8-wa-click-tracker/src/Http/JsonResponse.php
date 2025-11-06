<?php

declare(strict_types=1);

namespace IW8\WA\Http;

if (!defined('ABSPATH')) {
    exit;
}

final class JsonResponse
{
    public static function ok(array $data, int $status = 200): \WP_REST_Response
    {
        return new \WP_REST_Response($data, $status);
    }
}
