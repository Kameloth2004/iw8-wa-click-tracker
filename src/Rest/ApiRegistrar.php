<?php

/**
 * IW8 – WA Click Tracker
 * REST API Registrar (skeleton)
 */

declare(strict_types=1);

namespace IW8\WA\Rest;

use WP_REST_Server;
use IW8\WaClickTracker\Rest\PingController;

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
        $enforcer = new \IW8\WA\Security\HttpsEnforcer();
        $auth     = new \IW8\WA\Security\TokenAuthenticator();

        // Controller do /ping
        $ping = new PingController(
            new \IW8\WA\Services\TimeProvider(),
            new \IW8\WA\Services\LimitsProvider(),
            $auth
        );

        $permission = function (\WP_REST_Request $request) use ($enforcer, $auth) {
            $https = $enforcer->enforce();
            if (is_wp_error($https)) {
                return $https;
            }
            $ok = $auth->validate($request);
            return is_wp_error($ok) ? $ok : true;
        };

        // /wp-json/iw8-wa/v1/ping
        register_rest_route($this->namespace, '/ping', [
            'methods'  => 'GET',
            'callback' => [$ping, 'handle'],
            'permission_callback' => $permission,
            'args' => [],
        ]);

        // /wp-json/iw8-wa/v1/clicks
        $limits     = new \IW8\WA\Services\LimitsProvider();
        $validator  = new \IW8\WA\Validation\RequestValidator($limits);
        $cursor     = new \IW8\WA\Validation\CursorCodec();
        $repo       = new \IW8\WA\Repositories\ClickRepository();
        $clicksCtrl = new ClicksController($limits, $validator, $cursor, $repo);

        register_rest_route($this->namespace, '/clicks', [
            'methods'  => 'GET',
            'callback' => function (\WP_REST_Request $request) use ($clicksCtrl) {
                return $clicksCtrl->handle($request);
            },
            'permission_callback' => $permission,
            'args' => [],
        ]);

        // POST em /ping deve retornar 405 (em vez de 404)
        register_rest_route($this->namespace, '/ping', [
            'methods'             => WP_REST_Server::CREATABLE, // POST
            'callback'            => [PingController::class, 'method_not_allowed'],
            'permission_callback' => '__return_true', // não autentica; resposta é 405
        ]);
    }
}
