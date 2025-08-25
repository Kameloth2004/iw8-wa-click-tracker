<?php
/**
 * Página de configurações do plugin
 *
 * @package IW8_WaClickTracker\Admin\Pages
 * @version 1.3.0
 */

namespace IW8\WaClickTracker\Admin\Pages;

// Prevenir acesso direto
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Classe SettingsPage
 */
class SettingsPage
{
    /**
     * Construtor da classe
     */
    public function __construct()
    {
        // Processar formulário se enviado
        $this->process_form();
    }

    /**
     * Renderizar a página
     *
     * @return void
     */
    public function render()
    {
        // Verificar se as funções do WordPress estão disponíveis
        if (!function_exists('current_user_can') || !function_exists('wp_die') || !function_exists('get_option')) {
            return;
        }

        // Verificar permissões
        if (!current_user_can('manage_options')) {
            wp_die(__('Você não tem permissão para acessar esta página.', 'iw8-wa-click-tracker'));
        }

        // Obter valores atuais das opções
        $phone = get_option('iw8_wa_phone', '');
        $debug = get_option('iw8_wa_debug', false);
        $no_beacon = get_option('iw8_wa_no_beacon', true);

        ?>
        <div class="wrap">
            <h1><?php _e('Configurações - WA Cliques', 'iw8-wa-click-tracker'); ?></h1>
            
            <?php $this->render_admin_notices(); ?>
            
            <div class="iw8-settings-form">
                <form method="post" action="">
                    <?php wp_nonce_field('iw8_wa_settings'); ?>
                    
                    <table class="form-table">
                        <tbody>
                            <tr>
                                <th scope="row">
                                    <label for="iw8_wa_phone"><?php _e('Telefone', 'iw8-wa-click-tracker'); ?></label>
                                </th>
                                <td>
                                    <input type="text" 
                                           id="iw8_wa_phone" 
                                           name="iw8_wa_phone" 
                                           value="<?php echo esc_attr($phone); ?>" 
                                           class="regular-text" 
                                           pattern="[0-9]+" 
                                           maxlength="15" 
                                           required />
                                    <p class="description">
                                        <?php _e('Digite apenas números (DDI + DDD + número). Exemplo: 554832389838', 'iw8-wa-click-tracker'); ?>
                                    </p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">
                                    <label for="iw8_wa_debug"><?php _e('Modo Debug', 'iw8-wa-click-tracker'); ?></label>
                                </th>
                                <td>
                                    <input type="checkbox" 
                                           id="iw8_wa_debug" 
                                           name="iw8_wa_debug" 
                                           value="1" 
                                           <?php checked($debug, true); ?> />
                                    <label for="iw8_wa_debug">
                                        <?php _e('Ativar logs de debug no console do navegador', 'iw8-wa-click-tracker'); ?>
                                    </label>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">
                                    <label for="iw8_wa_no_beacon"><?php _e('Desabilitar Beacon', 'iw8-wa-click-tracker'); ?></label>
                                </th>
                                <td>
                                    <input type="checkbox" 
                                           id="iw8_wa_no_beacon" 
                                           name="iw8_wa_no_beacon" 
                                           value="1" 
                                           <?php checked($no_beacon, true); ?> />
                                    <label for="iw8_wa_no_beacon">
                                        <?php _e('Usar fetch em vez de sendBeacon (recomendado para compatibilidade)', 'iw8-wa-click-tracker'); ?>
                                    </label>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                    
                    <p class="submit">
                        <input type="submit" 
                               name="submit" 
                               id="submit" 
                               class="button button-primary" 
                               value="<?php _e('Salvar Configurações', 'iw8-wa-click-tracker'); ?>" />
                    </p>
                </form>
            </div>
        </div>
        <?php
    }

    /**
     * Processar formulário enviado
     *
     * @return void
     */
    private function process_form()
    {
        // Verificar se as funções do WordPress estão disponíveis
        if (!function_exists('isset') || !function_exists('check_admin_referer') || !function_exists('current_user_can')) {
            return;
        }

        // Verificar se formulário foi enviado
        if (!isset($_POST['submit'])) {
            return;
        }

        // Verificar nonce e permissões
        if (!check_admin_referer('iw8_wa_settings') || !current_user_can('manage_options')) {
            wp_die(__('Ação não autorizada.', 'iw8-wa-click-tracker'));
        }

        // Processar telefone
        $phone = isset($_POST['iw8_wa_phone']) ? sanitize_text_field($_POST['iw8_wa_phone']) : '';
        $phone = preg_replace('/[^0-9]/', '', $phone);

        // Validar telefone
        if (strlen($phone) < 10 || strlen($phone) > 15) {
            add_settings_error(
                'iw8_wa_phone',
                'iw8_wa_phone_error',
                __('Telefone deve ter entre 10 e 15 dígitos.', 'iw8-wa-click-tracker'),
                'error'
            );
            return;
        }

        // Processar flags
        $debug = isset($_POST['iw8_wa_debug']) ? 1 : 0;
        $no_beacon = isset($_POST['iw8_wa_no_beacon']) ? 1 : 0;

        // Salvar opções
        update_option('iw8_wa_phone', $phone);
        update_option('iw8_wa_debug', $debug);
        update_option('iw8_wa_no_beacon', $no_beacon);

        // Mensagem de sucesso
        add_settings_error(
            'iw8_wa_settings',
            'iw8_wa_settings_updated',
            __('Configurações salvas com sucesso!', 'iw8-wa-click-tracker'),
            'success'
        );
    }

    /**
     * Renderizar notices administrativos
     *
     * @return void
     */
    private function render_admin_notices()
    {
        // Verificar se as funções do WordPress estão disponíveis
        if (!function_exists('add_settings_error') || !function_exists('settings_errors')) {
            return;
        }

        // Verificar se telefone está configurado
        if (!\IW8\WaClickTracker\Utils\Helpers::isPhoneConfigured()) {
            ?>
            <div class="notice notice-warning">
                <p>
                    <strong><?php _e('Atenção:', 'iw8-wa-click-tracker'); ?></strong>
                    <?php _e('O telefone não está configurado. O rastreamento de cliques não funcionará até que um telefone válido seja configurado.', 'iw8-wa-click-tracker'); ?>
                </p>
            </div>
            <?php
        }

        // Mostrar notices de configurações
        settings_errors('iw8_wa_settings');
        settings_errors('iw8_wa_phone');
    }
}
