<?php

declare(strict_types=1);

namespace IW8\WA\Rest;

use IW8\WA\Services\TimeProvider;
use IW8\WA\Services\LimitsProvider;
use IW8\WA\Security\TokenAuthenticator;
use IW8\WA\Security\RateLimiter;
use IW8\WA\Http\ErrorFactory;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Controlador do endpoint /ping
 *
 * Retorna informações de saúde do serviço e confirma autenticação.
 */
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

    public function handle(\WP_REST_Request $request): \WP_REST_Response
    {
        // Rate limit por token + rota
        $token = $this->auth->extractToken($request);
        $rl    = new RateLimiter();
        $key   = $rl->keyFor($request->get_route(), (string) $token);
        $meta  = $rl->check($key, $this->limits->getRatePerMinute(), 60);

        if (!$meta['allowed']) {
            // Importante: ErrorFactory::make deve retornar \WP_REST_Response
            $resp = ErrorFactory::make(
                'too_many_requests',
                'Limite de taxa excedido.',
                429,
                [],
                $meta['retry_after_seconds'] ?? null
            );
            return $rl->applyHeaders($resp, $meta);
        }

        $home   = get_home_url();
        $domain = wp_parse_url($home, PHP_URL_HOST);

        $payload = [
            'service'  => 'iw8-wa-click-tracker',
            'version'  => defined('IW8_WA_CLICK_TRACKER_VERSION') ? IW8_WA_CLICK_TRACKER_VERSION : 'unknown',
            'time_utc' => $this->time->nowIsoUtc(),
            'site'     => [
                'domain'  => $domain ?: null,
                'wp_home' => $home,
            ],
            'auth' => [
                'token_scope' => 'domain',
                'token_last4' => $this->auth->last4($token),
            ],
            'limits' => [
                'rate_per_minute'   => $this->limits->getRatePerMinute(),
                'max_page_size'     => $this->limits->getMaxPageSize(),
                'default_page_size' => $this->limits->getDefaultPageSize(),
                'max_lookback_days' => $this->limits->getMaxLookbackDays(),
            ],
            'pagination' => [
                'ordering'           => $this->limits->getCanonicalOrdering(),
                'cursor_semantics'   => $this->limits->getCursorSemantics(),
                'cursor_ttl_seconds' => $this->limits->getCursorTtlSeconds(),
            ],
        ];

        $resp = new \WP_REST_Response($payload, 200);
        return $rl->applyHeaders($resp, $meta);
    }

    /**
     * Responde 405 para métodos não permitidos em /ping.
     */
    public static function method_not_allowed(\WP_REST_Request $request): \WP_REST_Response
    {
        $response = new \WP_REST_Response(
            [
                'ok'      => false,
                'error'   => 'method_not_allowed',
                'message' => 'Method not allowed. Use GET.',
            ],
            405
        );
        $response->header('Allow', 'GET');
        return $response;
    }
}
