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
        $enforcer = new \IW8\WA\Security\HttpsEnforcer();
        $auth     = new \IW8\WA\Security\TokenAuthenticator();

        // /ping
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

        register_rest_route($this->namespace, '/ping', [
            'methods'  => 'GET',
            'callback' => [$ping, 'handle'],
            'permission_callback' => $permission,
            'args' => [],
        ]);

        // /clicks (agora com validação e resposta vazia)
        $limits     = new \IW8\WA\Services\LimitsProvider();
        $validator  = new \IW8\WA\Validation\RequestValidator($limits);
        $cursor     = new \IW8\WA\Validation\CursorCodec();
        $clicks     = new ClicksController($limits, $validator, $cursor);

        register_rest_route($this->namespace, '/clicks', [
            'methods'  => 'GET',
            'callback' => [$clicks, 'handle'],
            'permission_callback' => $permission,
            'args' => [],
        ]);
    }
}
