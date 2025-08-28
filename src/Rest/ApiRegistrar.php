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
        // /wp-json/iw8-wa/v1/ping
        register_rest_route($this->namespace, '/ping', [
            'methods'  => 'GET',
            'callback' => [$this, 'notImplemented'],
            'permission_callback' => '__return_true', // Será substituído por autenticação no passo seguinte
            'args' => [],
        ]);

        // /wp-json/iw8-wa/v1/clicks
        register_rest_route($this->namespace, '/clicks', [
            'methods'  => 'GET',
            'callback' => [$this, 'notImplemented'],
            'permission_callback' => '__return_true', // Será substituído por autenticação no passo seguinte
            'args' => [],
        ]);
    }

    /**
     * Placeholder até implementarmos os Controllers e a segurança.
     * Retorna 501 para evitar comportamento silencioso.
     */
    public function notImplemented(\WP_REST_Request $request)
    {
        return new \WP_Error(
            'not_implemented',
            'Endpoint definido, porém ainda não implementado nesta versão de desenvolvimento.',
            ['status' => 501]
        );
    }
}
