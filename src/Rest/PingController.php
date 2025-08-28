<?php

declare(strict_types=1);

namespace IW8\WA\Rest;

use IW8\WA\Services\TimeProvider;
use IW8\WA\Services\LimitsProvider;
use IW8\WA\Security\TokenAuthenticator;

if (!defined('ABSPATH')) {
    exit;
}

final class PingController
{
    private TimeProvider $time;
    private LimitsProvider $limits;
    private TokenAuthenticator $auth;

    public function __construct(TimeProvider $time, LimitsProvider $limits, TokenAuthenticator $auth)
    {
        $this->time   = $time;
        $this->limits = $limits;
        $this->auth   = $auth;
    }

    /** Handler do GET /ping (agora com token_last4 preenchido) */
    public function handle(\WP_REST_Request $request)
    {
        $home = get_home_url();
        $domain = wp_parse_url($home, PHP_URL_HOST);

        $token = $this->auth->extractToken($request);
        $payload = [
            'service' => 'iw8-wa-click-tracker',
            'version' => defined('IW8_WA_CLICK_TRACKER_VERSION') ? IW8_WA_CLICK_TRACKER_VERSION : 'unknown',
            'time_utc' => $this->time->nowIsoUtc(),
            'site' => [
                'domain'  => $domain ?: null,
                'wp_home' => $home,
            ],
            'auth' => [
                'token_scope' => 'domain',
                'token_last4' => $this->auth->last4($token),
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
