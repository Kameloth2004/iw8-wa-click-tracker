<?php

declare(strict_types=1);

namespace IW8\WA\Rest;

use IW8\WA\Services\TimeProvider;
use IW8\WA\Services\LimitsProvider;

if (!defined('ABSPATH')) {
    exit;
}

final class PingController
{
    private TimeProvider $time;
    private LimitsProvider $limits;

    public function __construct(TimeProvider $time, LimitsProvider $limits)
    {
        $this->time   = $time;
        $this->limits = $limits;
    }

    /** Handler do GET /ping (sem auth/https enforcement ainda) */
    public function handle(\WP_REST_Request $request)
    {
        $home = get_home_url();
        $domain = wp_parse_url($home, PHP_URL_HOST);

        $payload = [
            'service' => 'iw8-wa-click-tracker',
            'version' => defined('IW8_WA_CLICK_TRACKER_VERSION') ? IW8_WA_CLICK_TRACKER_VERSION : 'unknown',
            'time_utc' => $this->time->nowIsoUtc(),
            'site' => [
                'domain'  => $domain ?: null,
                'wp_home' => $home,
            ],
            'auth' => [
                // Será populado de fato quando adicionarmos o TokenAuthenticator.
                'token_scope' => 'domain',
                'token_last4' => null,
            ],
            'limits' => [
                'rate_per_minute'  => $this->limits->getRatePerMinute(),
                'max_page_size'    => $this->limits->getMaxPageSize(),
                'default_page_size' => $this->limits->getDefaultPageSize(),
                'max_lookback_days' => $this->limits->getMaxLookbackDays(),
            ],
            'pagination' => [
                'ordering'          => $this->limits->getCanonicalOrdering(),
                'cursor_semantics'  => $this->limits->getCursorSemantics(),
                'cursor_ttl_seconds' => $this->limits->getCursorTtlSeconds(),
            ],
        ];

        return new \WP_REST_Response($payload, 200);
    }
}
