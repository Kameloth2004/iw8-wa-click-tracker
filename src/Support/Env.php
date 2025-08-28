<?php

declare(strict_types=1);

namespace IW8\WA\Support;

if (!defined('ABSPATH')) {
    exit;
}

final class Env
{
    public static function isProduction(): bool
    {
        $home = get_home_url();
        $host = wp_parse_url($home, PHP_URL_HOST) ?: '';
        $isLocalHost = self::isLocalHost($host);

        // Se WP_DEBUG estiver true, considera não-produção
        $debug = defined('WP_DEBUG') && WP_DEBUG;

        return !$isLocalHost && !$debug;
    }

    private static function isLocalHost(string $host): bool
    {
        $h = strtolower($host);
        return $h === 'localhost'
            || $h === '127.0.0.1'
            || str_ends_with($h, '.local')
            || str_ends_with($h, '.test');
    }
}
