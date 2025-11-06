<?php
// ARQUIVO NOVO: includes/Admin/Actions/SavePhoneHandler.php
// Responsável por salvar o telefone via admin-post sem namespaces.

if (! defined('ABSPATH')) {
    exit;
}

/**
 * POST handler para salvar o telefone.
 * Envie o formulário para: wp-admin/admin-post.php?action=iw8ct_save_phone
 * Inclua nonce: wp_nonce_field('iw8ct_save_phone')
 * Campo: <input name="iw8_wa_phone">
 */
function iw8ct_handle_save_phone()
{
    if (! current_user_can('manage_options')) {
        wp_die(__('Acesso negado.', 'iw8-wa-click-tracker'), 403);
    }
    check_admin_referer('iw8ct_save_phone');

    $raw    = isset($_POST['iw8_wa_phone']) ? (string) wp_unslash($_POST['iw8_wa_phone']) : '';
    $raw    = sanitize_text_field($raw);
    $digits = preg_replace('/\D+/', '', $raw);

    // Validação mínima (ajuste os limites se quiser)
    if ($digits !== '' && (strlen($digits) < 10 || strlen($digits) > 15)) {
        $back = wp_get_referer();
        if (! $back) {
            // Fallback: volte para a página de opções geral do plugin (ajuste o slug da sua página se necessário)
            $back = admin_url('admin.php?page=iw8-wa-settings');
        }
        $url = add_query_arg(
            array(
                'iw8_notice'    => 'phone_invalid',
                'prefill_phone' => rawurlencode($raw),
            ),
            $back
        );
        wp_safe_redirect($url);
        exit;
    }

    // Persiste telefone (principal) e espelho compat (se código antigo ler esse nome)
    update_option('iw8_wa_phone', $digits);
    update_option('iw8_wa_phone_number', $digits);

    // Flags opcionais (se seu form enviar estes campos)
    if (isset($_POST['iw8_wa_debug'])) {
        update_option('iw8_wa_debug', 1);
    } elseif (isset($_POST['_iw8_wa_debug_present'])) {
        update_option('iw8_wa_debug', 0);
    }
    if (isset($_POST['iw8_wa_no_beacon'])) {
        update_option('iw8_wa_no_beacon', 1);
    } elseif (isset($_POST['_iw8_wa_no_beacon_present'])) {
        update_option('iw8_wa_no_beacon', 0);
    }

    $back = wp_get_referer();
    if (! $back) {
        $back = admin_url('admin.php?page=iw8-wa-settings'); // ajuste se o slug for diferente
    }
    $url = add_query_arg('iw8_notice', 'saved', $back);
    wp_safe_redirect($url);
    exit;
}
add_action('admin_post_iw8ct_save_phone', 'iw8ct_handle_save_phone');
