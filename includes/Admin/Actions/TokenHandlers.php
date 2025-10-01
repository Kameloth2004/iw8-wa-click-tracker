<?php
// ARQUIVO NOVO: wp-content/plugins/iw8-wa-click-tracker/includes/Admin/Actions/TokenHandlers.php

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Gera/rotaciona o token novo (iw8_click_token) e volta para a página chamadora.
 */
function iw8ct_handle_rotate_token_ui()
{
    if (! current_user_can('manage_options')) {
        wp_die(__('Acesso negado.', 'iw8-wa-click-tracker'), 403);
    }
    check_admin_referer('iw8ct_rotate_token_ui');

    // Gera 64 hex com fallbacks seguros
    $token = '';
    if (function_exists('random_bytes')) {
        try {
            $token = bin2hex(random_bytes(32));
        } catch (\Throwable $e) {
            $token = '';
        }
    }
    if ($token === '' && function_exists('openssl_random_pseudo_bytes')) {
        $strong = false;
        $bytes  = openssl_random_pseudo_bytes(32, $strong);
        if ($bytes !== false && $strong === true) {
            $token = bin2hex($bytes);
        }
    }
    if ($token === '') {
        $alphabet = '0123456789abcdef';
        for ($i = 0; $i < 64; $i++) {
            $token .= $alphabet[wp_rand(0, 15)];
        }
    }

    // Cria com autoload=no na primeira vez
    $exists = get_option('iw8_click_token', null);
    if (null === $exists) {
        add_option('iw8_click_token', $token, '', 'no');
    } else {
        update_option('iw8_click_token', $token);
    }

    // Timestamp (autoload=no na primeira vez)
    $ts_exists = get_option('iw8_click_token_rotated_at', null);
    if (null === $ts_exists) {
        add_option('iw8_click_token_rotated_at', time(), '', 'no');
    } else {
        update_option('iw8_click_token_rotated_at', time());
    }

    $back = wp_get_referer();
    if (! $back) {
        $back = admin_url('admin.php?page=iw8-wa-settings');
    } // ajuste se outro slug
    $url = add_query_arg('iw8_notice', 'rotated', $back);
    wp_safe_redirect($url);
    exit;
}
add_action('admin_post_iw8ct_rotate_token_ui', 'iw8ct_handle_rotate_token_ui');

/**
 * Exporta JSON com base_url + token efetivo (prioriza novo; fallback legado) e faz download.
 */
function iw8ct_handle_export_token_ui()
{
    if (! current_user_can('manage_options')) {
        wp_die(__('Acesso negado.', 'iw8-wa-click-tracker'), 403);
    }
    check_admin_referer('iw8ct_export_token_ui');

    $token_new    = (string) get_option('iw8_click_token', '');
    $token_legacy = (string) get_option('iw8_wa_domain_token', '');
    $token        = $token_new !== '' ? $token_new : ($token_legacy !== '' ? $token_legacy : '');

    if ($token === '') {
        wp_die(__('Nenhum token disponível para exportação.', 'iw8-wa-click-tracker'), 400);
    }

    $payload = array(
        'base_url' => home_url('/'),
        'token'    => $token,
        'updated'  => gmdate('c'),
        'plugin'   => array(
            'name'    => 'iw8-wa-click-tracker',
            'version' => defined('IW8_WA_CT_VERSION') ? IW8_WA_CT_VERSION : '1.4.3',
        ),
    );

    nocache_headers();
    header('Content-Type: application/json; charset=utf-8');
    header('Content-Disposition: attachment; filename=iw8-click-token.json');
    echo wp_json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    exit;
}
add_action('admin_post_iw8ct_export_token_ui', 'iw8ct_handle_export_token_ui');
