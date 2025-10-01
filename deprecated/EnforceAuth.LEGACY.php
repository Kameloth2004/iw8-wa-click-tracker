<?php
/* 
 * ARQUIVO: wp-content/plugins/iw8-wa-click-tracker/includes/REST/EnforceAuth.php
 * Função: intercepta requisições REST do namespace iw8-wa/v1 e aplica o TokenAuthenticator
 */

if (! defined('ABSPATH')) {
    exit;
}

if (! class_exists('IW8_WA_EnforceAuth')) {

    class IW8_WA_EnforceAuth
    {

        public static function init()
        {
            /**
             * Executa antes dos callbacks das rotas.
             * Prioridade baixa (5) para rodar cedo, e com 3 args para ler $request.
             */
            add_filter('rest_request_before_callbacks', array(__CLASS__, 'enforce'), 5, 3);
        }

        /**
         * @param mixed           $response  Valor inicial (normalmente null)
         * @param array           $handler   Dados do handler selecionado
         * @param WP_REST_Request $request   Requisição atual
         * @return mixed|WP_Error
         */
        public static function enforce($response, $handler, $request)
        {
            // Somente nosso namespace
            $route = $request->get_route(); // ex: /iw8-wa/v1/ping
            if (strpos($route, '/iw8-wa/v1/') === false) {
                return $response;
            }

            // Garante que o autenticador está carregado
            if (! class_exists('IW8_WA_TokenAuthenticator')) {
                // Tenta carregar dinamicamente (caso o require no bootstrap falhe)
                $maybe = plugin_dir_path(dirname(__FILE__)) . 'REST/TokenAuthenticator.php';
                if (file_exists($maybe)) {
                    require_once $maybe;
                }
            }

            if (! class_exists('IW8_WA_TokenAuthenticator')) {
                // Falha crítica — sem autenticador, bloqueia por segurança
                if (! class_exists('WP_Error')) {
                    require_once ABSPATH . WPINC . '/class-wp-error.php';
                }
                return new WP_Error(
                    'iw8_wa_auth_unavailable',
                    __('Autenticação indisponível.', 'iw8-wa-click-tracker'),
                    array('status' => 503)
                );
            }

            // Valida o token (novo -> legado). Retorna WP_Error em caso de falha.
            $result = IW8_WA_TokenAuthenticator::validate_request($request);
            if (is_wp_error($result)) {
                return $result;
            }

            // OK — segue o fluxo normal
            return $response;
        }
    }

    // Bootstrap
    IW8_WA_EnforceAuth::init();
}
