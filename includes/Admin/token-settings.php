<?php

/**
 * IW8 Click Tracker — Página “API Token”
 *
 * - Submenu em Configurações → IW8 Click Tracker (slug: iw8-click-tracker)
 * - Campo “Token atual” somente leitura + botão “Copiar”
 * - Botão “Gerar/Rotacionar Token” (random_bytes + bin2hex, 32 bytes => 64 hex)
 * - Texto de ajuda: “Use este token no header X-IW8-Token”
 * - “Última rotação” (timestamp)
 * - Segurança: current_user_can('manage_options'), nonce (check_admin_referer), admin_post handler
 * - Persistência: get_option('iw8_click_token'), update_option('iw8_click_token', ...),
 *                 get_option('iw8_click_token_rotated_at')
 *
 * IMPORTANTE:
 *   Neste Item 1 criamos apenas a interface/admin. Se seus endpoints ainda leem
 *   `iw8_wa_domain_token`, no Item 2 faremos o TokenAuthenticator buscar primeiro
 *   `iw8_click_token` e, em fallback, `iw8_wa_domain_token` (compatibilidade).
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Registra o submenu em Configurações → IW8 Click Tracker
 */
function iw8ct_register_settings_page()
{
    // Renderização e registro da página só para quem pode gerenciar opções
    if (!current_user_can('manage_options')) {
        return;
    }

    add_options_page(
        __('IW8 Click Tracker – API Token', 'iw8-wa-click-tracker'), // Título da página
        __('IW8 Click Tracker', 'iw8-wa-click-tracker'),             // Rótulo do menu
        'manage_options',                                            // Capability
        'iw8-click-tracker',                                         // Slug
        'iw8ct_render_settings_page'                                 // Callback de render
    );
}
add_action('admin_menu', 'iw8ct_register_settings_page');

/**
 * Handler da ação admin_post para rotação/geração do token
 * - Gera 32 bytes aleatórios (bin2hex => 64 chars)
 * - Atualiza opções no banco
 * - Redireciona com notice
 */
function iw8ct_handle_rotate_token()
{
    if (!current_user_can('manage_options')) {
        wp_die(__('Acesso negado.', 'iw8-wa-click-tracker'), 403);
    }

    // Valida nonce da ação
    check_admin_referer('iw8ct_rotate_token');

    // Gera token seguro (64 hex) com fallbacks:
    // 1) random_bytes (PHP 7+) → 2) openssl_random_pseudo_bytes → 3) wp_rand()
    $token = '';

    if (function_exists('random_bytes')) {
        try {
            $token = bin2hex(random_bytes(32)); // 64 chars hex
        } catch (\Throwable $e) {
            $token = '';
        }
    }

    if ($token === '' && function_exists('openssl_random_pseudo_bytes')) {
        $strong = false;
        $bytes  = openssl_random_pseudo_bytes(32, $strong);
        if ($bytes !== false && $strong === true) {
            $token = bin2hex($bytes); // 64 chars hex
        }
    }

    // Fallback final (ambiente muito restrito): usa wp_rand() para gerar 64 hex
    if ($token === '') {
        if (!function_exists('iw8ct_generate_hex_fallback')) {
            /**
             * Gera uma string hex (0-9a-f) usando wp_rand() quando não há CSPRNG disponível.
             */
            function iw8ct_generate_hex_fallback($length = 64)
            {
                $alphabet = '0123456789abcdef';
                $out = '';
                for ($i = 0; $i < $length; $i++) {
                    $out .= $alphabet[wp_rand(0, 15)];
                }
                return $out;
            }
        }
        $token = iw8ct_generate_hex_fallback(64);
    }

    // Persiste sem autoload
    update_option('iw8_click_token', $token, false);
    update_option('iw8_click_token_rotated_at', time(), false);

    // Redireciona para a página com uma query indicando sucesso
    $url = add_query_arg(
        array('page' => 'iw8-click-tracker', 'iw8ct_notice' => 'rotated'),
        admin_url('options-general.php')
    );
    wp_safe_redirect($url);
    exit;
}
add_action('admin_post_iw8ct_rotate_token', 'iw8ct_handle_rotate_token');


/**
 * Renderiza a página “API Token”
 */
function iw8ct_render_settings_page()
{
    if (!current_user_can('manage_options')) {
        wp_die(__('Acesso negado.', 'iw8-wa-click-tracker'), 403);
    }

    // Exibe notice após rotação
    if (isset($_GET['iw8ct_notice']) && $_GET['iw8ct_notice'] === 'rotated') {
        add_settings_error(
            'iw8ct',
            'iw8ct_token_rotated',
            __('Token gerado/rotacionado com sucesso.', 'iw8-wa-click-tracker'),
            'updated'
        );
    }

    // Lê opções
    $token      = (string) get_option('iw8_click_token', '');
    $rotated_at = (int) get_option('iw8_click_token_rotated_at', 0);

    // Formata “Última rotação”
    $rotated_txt = $rotated_at > 0
        ? date_i18n(get_option('date_format') . ' ' . get_option('time_format'), $rotated_at)
        : __('Nunca', 'iw8-wa-click-tracker');

    // Action para o admin-post
    $action_url = admin_url('admin-post.php');
?>
    <div class="wrap">
        <h1><?php echo esc_html(get_admin_page_title()); ?></h1>

        <?php settings_errors('iw8ct'); ?>

        <p><?php echo esc_html__('Use este token no header X-IW8-Token.', 'iw8-wa-click-tracker'); ?></p>

        <table class="form-table" role="presentation">
            <tbody>
                <tr>
                    <th scope="row">
                        <label for="iw8ct_current_token"><?php echo esc_html__('Token atual', 'iw8-wa-click-tracker'); ?></label>
                    </th>
                    <td>
                        <input type="text"
                            id="iw8ct_current_token"
                            class="regular-text code"
                            readonly
                            value="<?php echo esc_attr($token); ?>"
                            placeholder="<?php echo esc_attr__('(ainda não gerado)', 'iw8-wa-click-tracker'); ?>">
                        <button type="button" class="button" id="iw8ct_copy_btn" <?php echo $token === '' ? 'disabled' : ''; ?>>
                            <?php echo esc_html__('Copiar', 'iw8-wa-click-tracker'); ?>
                        </button>
                        <p class="description">
                            <?php echo esc_html__('Cabeçalho esperado: X-IW8-Token: SEU_TOKEN', 'iw8-wa-click-tracker'); ?>
                        </p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php echo esc_html__('Última rotação', 'iw8-wa-click-tracker'); ?></th>
                    <td><span><?php echo esc_html($rotated_txt); ?></span></td>
                </tr>
            </tbody>
        </table>

        <form method="post" action="<?php echo esc_url($action_url); ?>">
            <?php wp_nonce_field('iw8ct_rotate_token'); ?>
            <input type="hidden" name="action" value="iw8ct_rotate_token">
            <?php submit_button(__('Gerar/Rotacionar Token', 'iw8-wa-click-tracker'), 'primary', 'submit', false); ?>
        </form>
    </div>

    <script>
        (function() {
            var btn = document.getElementById('iw8ct_copy_btn');
            if (!btn) return;
            btn.addEventListener('click', function() {
                var input = document.getElementById('iw8ct_current_token');
                if (!input || !input.value) return;
                input.select();
                input.setSelectionRange(0, 99999);
                try {
                    navigator.clipboard.writeText(input.value).then(function() {
                        btn.textContent = '<?php echo esc_js(__('Copiado!', 'iw8-wa-click-tracker')); ?>';
                        setTimeout(function() {
                            btn.textContent = '<?php echo esc_js(__('Copiar', 'iw8-wa-click-tracker')); ?>';
                        }, 1500);
                    }).catch(function() {
                        document.execCommand('copy');
                        btn.textContent = '<?php echo esc_js(__('Copiado!', 'iw8-wa-click-tracker')); ?>';
                        setTimeout(function() {
                            btn.textContent = '<?php echo esc_js(__('Copiar', 'iw8-wa-click-tracker')); ?>';
                        }, 1500);
                    });
                } catch (e) {
                    document.execCommand('copy');
                    btn.textContent = '<?php echo esc_js(__('Copiado!', 'iw8-wa-click-tracker')); ?>';
                    setTimeout(function() {
                        btn.textContent = '<?php echo esc_js(__('Copiar', 'iw8-wa-click-tracker')); ?>';
                    }, 1500);
                }
            });
        })();
    </script>
<?php
}
