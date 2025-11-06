<?php

declare(strict_types=1);

namespace IW8\WA\Security;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Rate limit simples por janela fixa (60s) usando transients.
 * Chave = hash(route|token). Compatível com PHP 7.x.
 */
final class RateLimiter
{
    /** Monta a chave do bucket (hash) */
    public function keyFor($route, $token)
    {
        $base = (string)$route . '|' . (string)$token;
        return 'iw8_rl_' . md5($base);
    }

    /**
     * Consome 1 unidade do bucket.
     * @return array { allowed(bool), limit(int), remaining(int), reset_seconds(int), retry_after_seconds(int) }
     */
    public function check($key, $limitPerMinute, $windowSeconds = 60)
    {
        $now = time();
        $data = get_transient($key);

        if (!is_array($data) || !isset($data['count'], $data['reset_at']) || $now >= (int)$data['reset_at']) {
            $data = array(
                'count'    => 0,
                'reset_at' => $now + (int)$windowSeconds,
            );
        }

        if ((int)$data['count'] >= (int)$limitPerMinute) {
            $retry = max(0, (int)$data['reset_at'] - $now);
            return array(
                'allowed'             => false,
                'limit'               => (int)$limitPerMinute,
                'remaining'           => 0,
                'reset_seconds'       => $retry,
                'retry_after_seconds' => $retry,
            );
        }

        // Consome 1
        $data['count'] = (int)$data['count'] + 1;
        $ttl = max(1, (int)$data['reset_at'] - $now);
        set_transient($key, $data, $ttl);

        $remaining = max(0, (int)$limitPerMinute - (int)$data['count']);

        return array(
            'allowed'             => true,
            'limit'               => (int)$limitPerMinute,
            'remaining'           => $remaining,
            'reset_seconds'       => $ttl,
            'retry_after_seconds' => 0,
        );
    }

    /** Aplica cabeçalhos padrão de rate limit na resposta */
    public function applyHeaders(\WP_REST_Response $resp, array $meta)
    {
        // Cabeçalhos de rate limit
        $resp->header('X-RateLimit-Limit', (string)$meta['limit']);
        $resp->header('X-RateLimit-Remaining', (string)$meta['remaining']);
        $resp->header('X-RateLimit-Reset', (string)$meta['reset_seconds']);

        // Retry-After somente quando bloqueado
        if (!$meta['allowed'] && isset($meta['retry_after_seconds'])) {
            $resp->header('Retry-After', (string)$meta['retry_after_seconds']);
        }

        // Cabeçalhos informativos extras
        if (defined('IW8_WA_CLICK_TRACKER_VERSION')) {
            $resp->header('X-Service-Version', (string)IW8_WA_CLICK_TRACKER_VERSION);
        }
        // Como o contrato define cursor forward-only
        $resp->header('X-Cursor-Semantics', 'forward_only');

        return $resp;
    }
}
