<?php

/** ARQUIVO: wp-content/plugins/iw8-wa-click-tracker/includes/Admin/Pages/SettingsPage.php
 * Objetivo: corrigir o botão “Copiar” (fallback sem navigator.clipboard) e
 * manter UI de Token + Telefone. Cola este arquivo inteiro.
 */

namespace IW8\WaClickTracker\Admin\Pages;

if (!defined('ABSPATH')) {
    exit;
}

class SettingsPage
{
    public function __construct() {}

    public function render()
    {
        if (!\function_exists('current_user_can') || !\current_user_can('manage_options')) {
            \wp_die(\__('Você não tem permissão para acessar esta página.', 'iw8-wa-click-tracker'));
        }

        // ===== Telefone =====
        $phone = (string) \get_option('iw8_wa_phone', '');
        if ($phone === '') {
            $alt = (string) \get_option('iw8_wa_phone_number', '');
            if ($alt !== '') {
                $phone = $alt;
            }
        }
        $prefill       = isset($_GET['prefill_phone']) ? \sanitize_text_field(\wp_unslash($_GET['prefill_phone'])) : '';
        $phone_value   = $prefill !== '' ? $prefill : $phone;
        $debug         = (int) \get_option('iw8_wa_debug', 0) === 1;
        $no_beacon     = (int) \get_option('iw8_wa_no_beacon', 1) === 1;

        // ===== Token =====
        $token_new     = (string) \get_option('iw8_click_token', '');
        $token_legacy  = (string) \get_option('iw8_wa_domain_token', '');
        $token_effect  = $token_new !== '' ? $token_new : ($token_legacy !== '' ? $token_legacy : '');
        $rotated_at    = (int) \get_option('iw8_click_token_rotated_at', 0);
        $rotated_txt   = $rotated_at > 0
            ? \date_i18n(\get_option('date_format') . ' ' . \get_option('time_format'), $rotated_at)
            : \__('Nunca', 'iw8-wa-click-tracker');
        $home          = \untrailingslashit(\home_url('/'));
        $masked_eff    = $token_effect ? self::mask_token($token_effect) : \__('(não definido)', 'iw8-wa-click-tracker');

        $rotate_url    = \wp_nonce_url(\admin_url('admin-post.php?action=iw8ct_rotate_token_ui'), 'iw8ct_rotate_token_ui');
        $export_url    = \wp_nonce_url(\admin_url('admin-post.php?action=iw8ct_export_token_ui'), 'iw8ct_export_token_ui');
?>
        <div class="wrap">
            <h1><?php \_e('Configurações - WA Cliques', 'iw8-wa-click-tracker'); ?></h1>

            <?php $this->render_admin_notices(); ?>

            <!-- ===== Token ===== -->
            <h2 style="margin-top:10px;"><?php \_e('API Token', 'iw8-wa-click-tracker'); ?></h2>
            <table class="widefat striped" style="max-width:960px;">
                <tbody>
                    <tr>
                        <th style="width:240px;"><?php \_e('Site (base URL)', 'iw8-wa-click-tracker'); ?></th>
                        <td><code><?php echo \esc_html($home . '/'); ?></code></td>
                    </tr>
                    <tr>
                        <th><?php \_e('Token em uso (novo → legado)', 'iw8-wa-click-tracker'); ?></th>
                        <td>
                            <span id="iw8-eff-masked"><code><?php echo \esc_html($masked_eff); ?></code></span>
                            <?php if ($token_effect): ?>
                                <button class="button" id="iw8-reveal-btn" type="button"><?php \_e('Revelar', 'iw8-wa-click-tracker'); ?></button>
                                <button class="button" id="iw8-copy-btn" type="button"><?php \_e('Copiar',  'iw8-wa-click-tracker'); ?></button>
                                <input type="hidden" id="iw8-eff-plain" value="<?php echo \esc_attr($token_effect); ?>">
                            <?php else: ?>
                                <em><?php \_e('Nenhum token encontrado. Gere um novo.', 'iw8-wa-click-tracker'); ?></em>
                            <?php endif; ?>
                            <p class="description"><?php \_e('Use este valor no cabeçalho: X-IW8-Token', 'iw8-wa-click-tracker'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th><?php \_e('Token novo (iw8_click_token)', 'iw8-wa-click-tracker'); ?></th>
                        <td><code><?php echo $token_new ? \esc_html(self::mask_token($token_new)) : \esc_html__('(vazio)', 'iw8-wa-click-tracker'); ?></code></td>
                    </tr>
                    <tr>
                        <th><?php \_e('Token legado (iw8_wa_domain_token)', 'iw8-wa-click-tracker'); ?></th>
                        <td><code><?php echo $token_legacy ? \esc_html(self::mask_token($token_legacy)) : \esc_html__('(vazio)', 'iw8-wa-click-tracker'); ?></code></td>
                    </tr>
                    <tr>
                        <th><?php \_e('Última rotação (novo)', 'iw8-wa-click-tracker'); ?></th>
                        <td><?php echo \esc_html($rotated_txt); ?></td>
                    </tr>
                </tbody>
            </table>

            <p style="margin:12px 0 18px;">
                <a href="<?php echo \esc_url($rotate_url); ?>" class="button button-primary">
                    <?php \_e('Gerar/Rotacionar token', 'iw8-wa-click-tracker'); ?>
                </a>
                <?php if ($token_effect): ?>
                    <a href="<?php echo \esc_url($export_url); ?>" class="button">
                        <?php \_e('Exportar (JSON)', 'iw8-wa-click-tracker'); ?>
                    </a>
                <?php endif; ?>
            </p>

            <!-- ===== Telefone ===== -->
            <h2><?php \_e('Telefone WhatsApp', 'iw8-wa-click-tracker'); ?></h2>
            <p class="description"><?php \_e('Informe apenas dígitos (ex.: 5599999999999).', 'iw8-wa-click-tracker'); ?></p>

            <div class="iw8-settings-form" style="max-width: 960px;">
                <form method="post" action="<?php echo \esc_url(\admin_url('admin-post.php')); ?>">
                    <?php \wp_nonce_field('iw8ct_save_phone'); ?>
                    <input type="hidden" name="action" value="iw8ct_save_phone">

                    <table class="form-table" role="presentation">
                        <tbody>
                            <tr>
                                <th scope="row"><label for="iw8_wa_phone"><?php \_e('Telefone (apenas dígitos)', 'iw8-wa-click-tracker'); ?></label></th>
                                <td>
                                    <input
                                        type="text"
                                        id="iw8_wa_phone"
                                        name="iw8_wa_phone"
                                        value="<?php echo \esc_attr($phone_value); ?>"
                                        class="regular-text code"
                                        inputmode="numeric"
                                        pattern="[0-9]{10,15}"
                                        maxlength="15"
                                        placeholder="5599999999999" />
                                    <p class="description"><?php \_e('Entre 10 e 15 dígitos. Ex.: 55 + DDD + número.', 'iw8-wa-click-tracker'); ?></p>
                                </td>
                            </tr>

                            <tr>
                                <th scope="row"><label for="iw8_wa_debug"><?php \_e('Modo Debug', 'iw8-wa-click-tracker'); ?></label></th>
                                <td>
                                    <input type="hidden" name="_iw8_wa_debug_present" value="1">
                                    <label>
                                        <input type="checkbox" id="iw8_wa_debug" name="iw8_wa_debug" value="1" <?php \checked($debug, true); ?> />
                                        <?php \_e('Ativar logs de diagnóstico', 'iw8-wa-click-tracker'); ?>
                                    </label>
                                </td>
                            </tr>

                            <tr>
                                <th scope="row"><label for="iw8_wa_no_beacon"><?php \_e('Desativar Beacon', 'iw8-wa-click-tracker'); ?></label></th>
                                <td>
                                    <input type="hidden" name="_iw8_wa_no_beacon_present" value="1">
                                    <label>
                                        <input type="checkbox" id="iw8_wa_no_beacon" name="iw8_wa_no_beacon" value="1" <?php \checked($no_beacon, true); ?> />
                                        <?php \_e('Não carregar beacon no front-end (usar fetch); recomendado para compatibilidade.', 'iw8-wa-click-tracker'); ?>
                                    </label>
                                </td>
                            </tr>
                        </tbody>
                    </table>

                    <p class="submit">
                        <input type="submit" name="submit" id="submit" class="button button-primary" value="<?php \_e('Salvar Configurações', 'iw8-wa-click-tracker'); ?>" />
                    </p>
                </form>
            </div>
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

                function fallbackCopy(text) {
                    try {
                        const ta = document.createElement('textarea');
                        ta.value = text;
                        ta.setAttribute('readonly', '');
                        ta.style.position = 'absolute';
                        ta.style.left = '-9999px';
                        document.body.appendChild(ta);
                        ta.select();
                        document.execCommand('copy');
                        document.body.removeChild(ta);
                        return true;
                    } catch (e) {
                        return false;
                    }
                }

                if (copyBtn && plainEl) {
                    copyBtn.addEventListener('click', async function() {
                        const text = plainEl.value || '';
                        if (!text) return;

                        // Usa Clipboard API se disponível; senão, fallback
                        const api = (typeof navigator !== 'undefined' && navigator.clipboard && navigator.clipboard.writeText) ?
                            navigator.clipboard.writeText(text) :
                            null;

                        if (api) {
                            try {
                                await api;
                                copyBtn.textContent = 'Copiado!';
                                setTimeout(() => copyBtn.textContent = 'Copiar', 1800);
                            } catch (e) {
                                if (fallbackCopy(text)) {
                                    copyBtn.textContent = 'Copiado!';
                                    setTimeout(() => copyBtn.textContent = 'Copiar', 1800);
                                } else {
                                    alert('Falha ao copiar (use Ctrl+C): ' + e);
                                }
                            }
                        } else {
                            if (fallbackCopy(text)) {
                                copyBtn.textContent = 'Copiado!';
                                setTimeout(() => copyBtn.textContent = 'Copiar', 1800);
                            } else {
                                alert('Falha ao copiar (use Ctrl+C)');
                            }
                        }
                    });
                }
            })();
        </script>
<?php
    }

    private function render_admin_notices()
    {
        if (isset($_GET['iw8_notice'])) {
            if ($_GET['iw8_notice'] === 'saved') {
                echo '<div class="notice notice-success is-dismissible"><p>' .
                    \esc_html__('Configurações salvas.', 'iw8-wa-click-tracker') .
                    '</p></div>';
            } elseif ($_GET['iw8_notice'] === 'phone_invalid') {
                echo '<div class="notice notice-error is-dismissible"><p>' .
                    \esc_html__('Telefone inválido. Use 10–15 dígitos.', 'iw8-wa-click-tracker') .
                    '</p></div>';
            } elseif ($_GET['iw8_notice'] === 'rotated') {
                echo '<div class="notice notice-success is-dismissible"><p>' .
                    \esc_html__('Token gerado/rotacionado com sucesso.', 'iw8-wa-click-tracker') .
                    '</p></div>';
            }
        }

        // Alerta se telefone não estiver configurado
        if (
            \class_exists('\IW8\WaClickTracker\Utils\Helpers')
            && \method_exists('\IW8\WaClickTracker\Utils\Helpers', 'isPhoneConfigured')
            && !\IW8\WaClickTracker\Utils\Helpers::isPhoneConfigured()
        ) {
            echo '<div class="notice notice-warning is-dismissible"><p><strong>' .
                \esc_html__('Atenção:', 'iw8-wa-click-tracker') .
                '</strong> ' .
                \esc_html__('O telefone não está configurado. O rastreamento de cliques pode não funcionar até você informar um número válido.', 'iw8-wa-click-tracker') .
                '</p></div>';
        }
    }

    private static function mask_token($t)
    {
        $t = (string) $t;
        $len = \strlen($t);
        if ($len <= 8) return $t;
        return \str_repeat('•', max(0, $len - 8)) . \substr($t, -8);
    }
}
