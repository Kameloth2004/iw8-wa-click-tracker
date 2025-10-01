<?php
/* ARQUIVO: wp-content/plugins/iw8-wa-click-tracker/includes/admin/token-settings.php
 * IW8 Click Tracker — Página “API Token”
 *
 * Ajustes:
 * - Exibe “Token em uso (novo → legado)” com botão Revelar/Copiar
 * - Botão “Gerar/Rotacionar Token” (64 hex) com autoload desativado
 * - Botão “Exportar (JSON)” com base_url + token efetivo
 * - Mantém slug: Configurações → IW8 Click Tracker (iw8-click-tracker)
 */

if (!defined('ABSPATH')) {
    exit;
}

/** Registra o submenu em Configurações → IW8 Click Tracker */
function iw8ct_register_settings_page()
{
    add_options_page(
        __('IW8 Click Tracker – API Token', 'iw8-wa-click-tracker'),
        __('IW8 Click Tracker', 'iw8-wa-click-tracker'),
        'manage_options',
        'iw8-click-tracker',
        'iw8ct_render_settings_page'
    );
}
add_action('admin_menu', 'iw8ct_register_settings_page');

/** Handler: gerar/rotacionar token novo (iw8_click_token) */
function iw8ct_handle_rotate_token()
{
    if (!current_user_can('manage_options')) {
        wp_die(__('Acesso negado.', 'iw8-wa-click-tracker'), 403);
    }
    check_admin_referer('iw8ct_rotate_token');

    // Gera 64 hex seguro (com fallbacks)
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
        // Fallback final via wp_rand()
        $alphabet = '0123456789abcdef';
        for ($i = 0; $i < 64; $i++) {
            $token .= $alphabet[wp_rand(0, 15)];
        }
    }

    // Persistência: criar com autoload = no na 1ª vez; depois atualizar
    $exists = get_option('iw8_click_token', null);
    if ($exists === null) {
        add_option('iw8_click_token', $token, '', 'no');
    } else {
        update_option('iw8_click_token', $token);
    }

    // Timestamp da rotação (autoload = no na 1ª vez)
    $ts_exists = get_option('iw8_click_token_rotated_at', null);
    if ($ts_exists === null) {
        add_option('iw8_click_token_rotated_at', time(), '', 'no');
    } else {
        update_option('iw8_click_token_rotated_at', time());
    }

    wp_safe_redirect(add_query_arg(
        array('page' => 'iw8-click-tracker', 'iw8ct_notice' => 'rotated'),
        admin_url('options-general.php')
    ));
    exit;
}
add_action('admin_post_iw8ct_rotate_token', 'iw8ct_handle_rotate_token');

/** Handler: exporta JSON com base_url + token efetivo (novo→legado) */
function iw8ct_handle_export_token()
{
    if (!current_user_can('manage_options')) {
        wp_die(__('Acesso negado.', 'iw8-wa-click-tracker'), 403);
    }
    check_admin_referer('iw8ct_export_token');

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
add_action('admin_post_iw8ct_export_token', 'iw8ct_handle_export_token');

/** Renderiza a página */
function iw8ct_render_settings_page()
{
    if (!current_user_can('manage_options')) {
        wp_die(__('Acesso negado.', 'iw8-wa-click-tracker'), 403);
    }

    if (isset($_GET['iw8ct_notice']) && $_GET['iw8ct_notice'] === 'rotated') {
        add_settings_error(
            'iw8ct',
            'iw8ct_token_rotated',
            __('Token gerado/rotacionado com sucesso.', 'iw8-wa-click-tracker'),
            'updated'
        );
    }

    $token_new    = (string) get_option('iw8_click_token', '');
    $token_legacy = (string) get_option('iw8_wa_domain_token', '');
    $effective    = $token_new !== '' ? $token_new : ($token_legacy !== '' ? $token_legacy : '');

    $rotated_at   = (int) get_option('iw8_click_token_rotated_at', 0);
    $rotated_txt  = $rotated_at > 0
        ? date_i18n(get_option('date_format') . ' ' . get_option('time_format'), $rotated_at)
        : __('Nunca', 'iw8-wa-click-tracker');

    $home       = untrailingslashit(home_url('/'));
    $masked_eff = $effective ? iw8ct_mask_token($effective) : __('(não definido)', 'iw8-wa-click-tracker');

    $rotate_url = wp_nonce_url(admin_url('admin-post.php?action=iw8ct_rotate_token'), 'iw8ct_rotate_token');
    $export_url = wp_nonce_url(admin_url('admin-post.php?action=iw8ct_export_token'), 'iw8ct_export_token');
?>
    <div class="wrap">
        <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
        <?php settings_errors('iw8ct'); ?>

        <p><?php echo esc_html__('Gerencie o token para autenticação dos endpoints REST.', 'iw8-wa-click-tracker'); ?></p>

        <table class="widefat striped" style="max-width: 920px;">
            <tbody>
                <tr>
                    <th style="width:240px;"><?php echo esc_html__('Site (base URL)', 'iw8-wa-click-tracker'); ?></th>
                    <td><code><?php echo esc_html($home . '/'); ?></code></td>
                </tr>
                <tr>
                    <th><?php echo esc_html__('Token em uso (novo → legado)', 'iw8-wa-click-tracker'); ?></th>
                    <td>
                        <span id="iw8-eff-masked"><code><?php echo esc_html($masked_eff); ?></code></span>
                        <?php if ($effective): ?>
                            <button class="button" id="iw8-reveal-btn" type="button"><?php esc_html_e('Revelar', 'iw8-wa-click-tracker'); ?></button>
                            <button class="button" id="iw8-copy-btn" type="button"><?php esc_html_e('Copiar', 'iw8-wa-click-tracker'); ?></button>
                            <input type="hidden" id="iw8-eff-plain" value="<?php echo esc_attr($effective); ?>">
                        <?php else: ?>
                            <em><?php esc_html_e('Nenhum token encontrado. Gere um novo.', 'iw8-wa-click-tracker'); ?></em>
                        <?php endif; ?>
                        <p class="description"><?php echo esc_html__('Use este valor no header: X-IW8-Token', 'iw8-wa-click-tracker'); ?></p>
                    </td>
                </tr>
                <tr>
                    <th><?php echo esc_html__('Token novo (iw8_click_token)', 'iw8-wa-click-tracker'); ?></th>
                    <td>
                        <code><?php echo $token_new ? esc_html(iw8ct_mask_token($token_new)) : esc_html__('(vazio)', 'iw8-wa-click-tracker'); ?></code>
                        <?php if ($token_new): ?>
                            <p class="description"><?php echo esc_html__('(novo possui prioridade sobre o legado)', 'iw8-wa-click-tracker'); ?></p>
                        <?php endif; ?>
                    </td>
                </tr>
                <tr>
                    <th><?php echo esc_html__('Token legado (iw8_wa_domain_token)', 'iw8-wa-click-tracker'); ?></th>
                    <td><code><?php echo $token_legacy ? esc_html(iw8ct_mask_token($token_legacy)) : esc_html__('(vazio)', 'iw8-wa-click-tracker'); ?></code></td>
                </tr>
                <tr>
                    <th><?php echo esc_html__('Última rotação (novo)', 'iw8-wa-click-tracker'); ?></th>
                    <td><?php echo esc_html($rotated_txt); ?></td>
                </tr>
            </tbody>
        </table>

        <p style="margin-top:14px;">
            <a href="<?php echo esc_url($rotate_url); ?>" class="button button-primary">
                <?php esc_html_e('Gerar/Rotacionar token', 'iw8-wa-click-tracker'); ?>
            </a>
            <?php if ($effective): ?>
                <a href="<?php echo esc_url($export_url); ?>" class="button">
                    <?php esc_html_e('Exportar (JSON)', 'iw8-wa-click-tracker'); ?>
                </a>
            <?php endif; ?>
        </p>

        <h2><?php esc_html_e('Exemplos de uso (curl)', 'iw8-wa-click-tracker'); ?></h2>
        <pre><?php echo esc_html(
                    '$ TOKEN="SEU_TOKEN_AQUI"
$ BASE="' . $home . '"
curl -i "$BASE/wp-json/iw8-wa/v1/ping" -H "X-IW8-Token: $TOKEN"
curl -s "$BASE/wp-json/iw8-wa/v1/clicks?since=2025-01-01T00:00:00Z&until=2025-12-31T23:59:59Z&limit=1&fields=id,clicked_at" -H "X-IW8-Token: $TOKEN"'
                ); ?></pre>
    </div>

    <script>
        (function() {
            const revealBtn = document.getElementById('iw8-reveal-btn');
            const copyBtn = document.getElementById('iw8-copy-btn');
            const maskedEl = document.getElementById('iw8-eff-masked');
            const plainEl = document.getElementById('iw8-eff-plain');

            if (revealBtn && maskedEl && plainEl) {
                revealBtn.addEventListener('click', function() {
                    maskedEl.innerHTML = '<code>' + plainEl.value + '</code>';
                });
            }
            if (copyBtn && plainEl) {
                copyBtn.addEventListener('click', async function() {
                    try {
                        await navigator.clipboard.writeText(plainEl.value);
                        copyBtn.textContent = 'Copiado!';
                        setTimeout(() => copyBtn.textContent = 'Copiar', 1800);
                    } catch (e) {
                        alert('Falha ao copiar: ' + e);
                    }
                });
            }
        })();
    </script>
<?php
}

/** Utilitário: mascara como ••••12345678 */
function iw8ct_mask_token($t)
{
    $t = (string) $t;
    $len = strlen($t);
    if ($len <= 8) return $t;
    return str_repeat('•', max(0, $len - 8)) . substr($t, -8);
}
