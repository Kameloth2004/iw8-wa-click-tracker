<?php

/**
 * Classe para gerenciar segurança e validações
 *
 * @package IW8_WaClickTracker\Core
 * @version 1.4.0
 */

namespace IW8\WaClickTracker\Core;

// Prevenir acesso direto
if (!defined('ABSPATH')) {
    exit;
}

final class Security
{
    public static function init(): void
    {
        // Barra HTTP nas rotas do plugin antes dos callbacks
        add_filter('rest_pre_dispatch', [self::class, 'restHttpsGate'], 5, 3);
    }

    public static function restHttpsGate($result, $server, $request)
    {
        // Em dev/local (seed), não força HTTPS
        if (!self::isProd()) {
            return $result;
        }

        $route = is_object($request) && method_exists($request, 'get_route')
            ? (string)$request->get_route()
            : (string)$request;

        if (!self::routeIsIw8($route)) {
            return $result;
        }

        if (!self::isHttpsRequest()) {
            return new \WP_Error(
                'rest_forbidden_http',
                __('HTTPS obrigatório em produção.', 'iw8-wa-click-tracker'),
                ['status' => 401]
            );
        }

        return $result;
    }

    private static function routeIsIw8(string $route): bool
    {
        // /iw8-wa/v1/...
        return str_starts_with($route, '/iw8-wa/v1/');
    }

    private static function isProd(): bool
    {
        // Considera seed/dev somente se a constante existir
        $seedDev = defined('IW8_WA_SEED_DEV') ? (bool) constant('IW8_WA_SEED_DEV') : false;
        return !$seedDev;
    }

    private static function isHttpsRequest(): bool
    {
        if (function_exists('is_ssl') && is_ssl()) {
            return true;
        }

        $proto = $_SERVER['HTTP_X_FORWARDED_PROTO'] ?? $_SERVER['X-Forwarded-Proto'] ?? null;
        if (is_string($proto) && stripos($proto, 'https') !== false) {
            return true;
        }

        if (!empty($_SERVER['HTTP_CF_VISITOR'])) {
            $cf = json_decode((string)$_SERVER['HTTP_CF_VISITOR'], true);
            if (is_array($cf) && strtolower($cf['scheme'] ?? '') === 'https') {
                return true;
            }
        }

        if ((string)($_SERVER['SERVER_PORT'] ?? '') === '443') {
            return true;
        }
        if (isset($_SERVER['HTTPS']) && strtolower((string)$_SERVER['HTTPS']) === 'on') {
            return true;
        }

        return false;
    }
}
