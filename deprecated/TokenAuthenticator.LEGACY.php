<?php

/**
 * IW8 – WA Click Tracker
 * TokenAuthenticator
 *
 * Responsável por validar o header X-IW8-Token nas rotas REST.
 * Compatibilidade: lê primeiro o novo option 'iw8_click_token' e,
 * se vazio, faz fallback para o legado 'iw8_wa_domain_token'.
 */

if (! defined('ABSPATH')) {
    exit;
}

class IW8_WA_TokenAuthenticator
{

    /**
     * Valida a requisição REST com base no header X-IW8-Token.
     *
     * @param WP_REST_Request $request
     * @return true|WP_Error  true em caso de sucesso, WP_Error em caso de falha
     */
    public static function validate_request($request)
    {
        $provided = self::get_token_from_request($request);
        if ('' === $provided) {
            return self::error('missing_token', __('Cabeçalho X-IW8-Token ausente ou vazio.', 'iw8-wa-click-tracker'), 401);
        }

        $expected = self::get_expected_token();
        if ('' === $expected) {
            // Nenhum token configurado (nem novo nem legado)
            return self::error('token_not_configured', __('Nenhum token configurado no site.', 'iw8-wa-click-tracker'), 503);
        }

        if (! hash_equals($expected, $provided)) {
            return self::error('invalid_token', __('Token inválido.', 'iw8-wa-click-tracker'), 401);
        }

        return true;
    }

    /**
     * Extrai o token do header (case-insensitive). Opcionalmente,
     * aceita ?x_iw8_token=... como fallback para diagnósticos.
     *
     * @param WP_REST_Request $request
     * @return string
     */
    private static function get_token_from_request($request)
    {
        $header = $request->get_header('x-iw8-token');
        if (is_string($header) && $header !== '') {
            return trim($header);
        }

        // Fallback opcional via query param (útil para testes pontuais)
        $qp = $request->get_param('x_iw8_token');
        if (is_string($qp) && $qp !== '') {
            return trim($qp);
        }

        return '';
    }

    /**
     * Lê o token esperado das opções do WP.
     * 1) Novo:  iw8_click_token
     * 2) Legado: iw8_wa_domain_token (fallback)
     *
     * @return string
     */
    private static function get_expected_token()
    {
        // Novo option (gerado/rotacionado na tela de admin)
        $token_new = get_option('iw8_click_token', '');
        $token_new = is_string($token_new) ? trim($token_new) : '';

        if ($token_new !== '') {
            return $token_new;
        }

        // Fallback legado para manter compatibilidade
        $token_legacy = get_option('iw8_wa_domain_token', '');
        $token_legacy = is_string($token_legacy) ? trim($token_legacy) : '';

        return $token_legacy;
    }

    /**
     * Helper para retornar WP_Error padronizado.
     *
     * @param string $code
     * @param string $message
     * @param int    $status
     * @return WP_Error
     */
    private static function error($code, $message, $status = 401)
    {
        if (! class_exists('WP_Error')) {
            require_once ABSPATH . WPINC . '/class-wp-error.php';
        }
        return new WP_Error("iw8_wa_{$code}", $message, array('status' => (int) $status));
    }
}
