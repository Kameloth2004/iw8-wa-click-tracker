<?php
// ARQUIVO: wp-content/plugins/iw8-wa-click-tracker/includes/REST/CompatPermission.php
// Objetivo: garantir que TODAS as rotas /iw8-wa/v1/* aceitem o novo token (iw8_click_token)
// mesmo que o permission_callback antigo valide apenas o legado.

if (! defined('ABSPATH')) {
    exit;
}

if (! class_exists('IW8_WA_CompatPermission')) {

    class IW8_WA_CompatPermission
    {

        public static function init()
        {
            // Executa na estrutura de endpoints já registrada
            add_filter('rest_endpoints', [__CLASS__, 'wrap_namespace_permissions'], 20, 1);
        }

        /**
         * Envolve os permission_callback de todas as rotas do namespace /iw8-wa/v1
         * para validar via TokenAuthenticator (novo→legado). Se válido, retorna TRUE
         * imediatamente e não deixa a verificação antiga bloquear.
         *
         * @param array $endpoints
         * @return array
         */
        public static function wrap_namespace_permissions($endpoints)
        {
            if (! is_array($endpoints)) {
                return $endpoints;
            }

            foreach ($endpoints as $route => &$handlers) {
                // Ex.: /iw8-wa/v1/ping, /iw8-wa/v1/clicks
                if (strpos($route, '/iw8-wa/v1/') !== 0) {
                    continue;
                }

                if (! is_array($handlers)) {
                    continue;
                }

                foreach ($handlers as $method => &$handler) {
                    // Cada $handler é um array com 'callback' e (opcional) 'permission_callback'
                    if (! is_array($handler) || ! isset($handler['callback'])) {
                        continue;
                    }

                    $orig_perm = isset($handler['permission_callback']) ? $handler['permission_callback'] : '__return_true';

                    // Substitui por um wrapper que valida o token novo→legado.
                    $handler['permission_callback'] = function ($request) use ($orig_perm) {
                        // Garante que a classe está carregada
                        if (! class_exists('IW8_WA_TokenAuthenticator')) {
                            // Sem autenticador? Cai para o permission original.
                            return is_callable($orig_perm) ? call_user_func($orig_perm, $request) : true;
                        }

                        $result = IW8_WA_TokenAuthenticator::validate_request($request);
                        if (is_wp_error($result)) {
                            // Token ausente/ inválido → bloqueia aqui mesmo
                            return $result;
                        }

                        // Token OK (novo ou legado) → autoriza SEM passar pela checagem antiga
                        return true;
                    };
                }
            }

            return $endpoints;
        }
    }

    IW8_WA_CompatPermission::init();
}
