<?php

/**
 * Classe para gerenciar assets (CSS/JS) do plugin
 *
 * @package IW8_WaClickTracker\Core
 * @version 1.4.3
 */

namespace IW8\WaClickTracker\Core;

// Prevenir acesso direto
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Classe Assets
 */
class Assets
{
    /**
     * Construtor da classe
     */
    public function __construct()
    {
        // Classe utilitária; sem estado
    }

    /**
     * Enfileirar assets do frontend
     *
     * @return void
     */
    public function enqueue_front(): void
    {
        // Apenas frontend (não admin)
        if (is_admin()) {
            return;
        }

        $debug = (bool) get_option('iw8_wa_debug', false);

        // Verificar se telefone está configurado e é válido
        $phone_raw = (string) get_option('iw8_wa_phone', '');
        if ($debug && function_exists('error_log')) {
            error_log('IW8_WA_CLICK_TRACKER DEBUG: Telefone obtido: ' . $phone_raw);
        }

        if (!$this->isValidPhone($phone_raw)) {
            if ($debug && function_exists('error_log')) {
                error_log('IW8_WA_CLICK_TRACKER DEBUG: Telefone inválido; não enfileirando tracker.');
            }
            return;
        }

        // Normaliza telefone (somente dígitos)
        $phone_digits = preg_replace('/[^0-9]/', '', $phone_raw);

        // Nonce da ação AJAX
        $nonce = wp_create_nonce('iw8_wa_click');

        // Preferência de envio (beacon → fetch → xhr)
        $no_beacon = (bool) get_option('iw8_wa_no_beacon', true);

        // Dados consumidos por assets/js/tracker.js
        $data = array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'action'   => 'iw8_wa_click',
            'nonce'    => $nonce,
            'phone'    => $phone_digits,
            'debug'    => $debug,
            'noBeacon' => $no_beacon,
            'user_id'  => get_current_user_id() ?: 0,
        );

        if ($debug && function_exists('error_log')) {
            error_log('IW8_WA_CLICK_TRACKER DEBUG: iw8WaData: ' . wp_json_encode($data));
        }

        // Registrar + enfileirar o tracker (sem dependências; no footer)
        $handle = 'iw8-wa-tracker';
        $src    = IW8_WA_CLICK_TRACKER_PLUGIN_URL . 'assets/js/tracker.js';
        $ver    = defined('IW8_WA_CT_VERSION') ? IW8_WA_CT_VERSION : (defined('IW8_WA_CLICK_TRACKER_VERSION') ? IW8_WA_CLICK_TRACKER_VERSION : '1.0.0');

        wp_register_script($handle, $src, array(), $ver, true);

        // Injetar iw8WaData ANTES do script
        wp_add_inline_script(
            $handle,
            'window.iw8WaData = ' . wp_json_encode($data) . ';',
            'before'
        );

        wp_enqueue_script($handle);

        if ($debug && function_exists('error_log')) {
            error_log('IW8_WA_CLICK_TRACKER DEBUG: Tracker enfileirado com sucesso.');
        }
    }

    /**
     * Enfileirar assets do admin (placeholder)
     *
     * @return void
     */
    public function enqueue_admin(): void
    {
        // Mantido como placeholder para futuro admin.css/admin.js se necessário.
        // Intencionalmente vazio para não carregar nada desnecessário no painel.
    }

    /**
     * Verificar se telefone é válido
     *
     * @param string $phone Número de telefone
     * @return bool
     */
    private function isValidPhone(string $phone): bool
    {
        if ($phone === '') {
            if ((bool) get_option('iw8_wa_debug', false) && function_exists('error_log')) {
                error_log('IW8_WA_CLICK_TRACKER DEBUG: Telefone vazio');
            }
            return false;
        }

        // Normalizar e verificar comprimento (10 a 15 dígitos)
        $digits_only = preg_replace('/[^0-9]/', '', $phone);
        $len = strlen($digits_only);
        $is_valid = ($len >= 10 && $len <= 15);

        if ((bool) get_option('iw8_wa_debug', false) && function_exists('error_log')) {
            error_log(sprintf(
                'IW8_WA_CLICK_TRACKER DEBUG: Telefone original: %s, dígitos: %s, válido: %s',
                $phone,
                $digits_only,
                $is_valid ? 'SIM' : 'NÃO'
            ));
        }

        return $is_valid;
    }
}
