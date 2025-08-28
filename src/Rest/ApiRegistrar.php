<?php

/**
 * IW8 – WA Click Tracker
 * REST API Registrar (skeleton)
 */

declare(strict_types=1);

namespace IW8\WA\Rest;

if (!defined('ABSPATH')) {
    exit;
}

final class ApiRegistrar
{
    /** @var string */
    private $namespace = 'iw8-wa/v1';

    /**
     * Registra rotas do namespace iw8-wa/v1.
     * Nesta etapa: callbacks "501 Not Implemented" como placeholders.
     */
    public function register(): void
    {
        // Controller do /ping
        $ping = new PingController(
            new \IW8\WA\Services\TimeProvider(),
            new \IW8\WA\Services\LimitsProvider()
        );

        // /wp-json/iw8-wa/v1/ping
        register_rest_route($this->namespace, '/ping', [
            'methods'  => 'GET',
            'callback' => [$ping, 'handle'],
            'permission_callback' => '__return_true', // auth/https entram na próxima etapa
            'args' => [],
        ]);

        // /wp-json/iw8-wa/v1/clicks (ainda placeholder 501)
        register_rest_route($this->namespace, '/clicks', [
            'methods'  => 'GET',
            'callback' => [$this, 'notImplemented'],
            'permission_callback' => '__return_true',
            'args' => [],
        ]);
    }
}
