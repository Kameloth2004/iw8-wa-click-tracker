<?php

/**
 * Classe para gerenciar assets (CSS/JS) do plugin
 *
 * @package IW8_WaClickTracker\Core
 * @version 1.3.0
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
        // Classe utilitária, não requer inicialização
    }

    /**
     * Enfileirar assets do frontend
     *
     * @return void
     */
    public function enqueue_front()
    {
        // Verificar se não é admin
        if (is_admin()) {
            return;
        }

        // Verificar se telefone está configurado e é válido
        $phone = get_option('iw8_wa_phone', '');

        // DEBUG: Log para verificar telefone
        if (function_exists('error_log')) {
            error_log('IW8_WA_CLICK_TRACKER DEBUG: Telefone obtido: ' . $phone);
        }

        if (!$this->isValidPhone($phone)) {
            if (function_exists('error_log')) {
                error_log('IW8_WA_CLICK_TRACKER DEBUG: Telefone inválido, não enfileirando assets');
            }
            return;
        }

        if (function_exists('error_log')) {
            error_log('IW8_WA_CLICK_TRACKER DEBUG: Telefone válido, enfileirando assets');
        }

        // Gerar nonce
        $nonce = wp_create_nonce('iw8_wa_click_nonce');

        // Obter configurações
        $debug = get_option('iw8_wa_debug', false);
        $no_beacon = get_option('iw8_wa_no_beacon', true);

        // Preparar dados para JavaScript
        $data = [
            'ajax_url' => admin_url('admin-ajax.php'),
            'action' => 'iw8_wa_click',
            'nonce' => $nonce,
            'phone' => preg_replace('/[^0-9]/', '', $phone),
            'debug' => (bool) $debug,
            'noBeacon' => (bool) $no_beacon
        ];

        if (function_exists('error_log')) {
            error_log('IW8_WA_CLICK_TRACKER DEBUG: Dados preparados: ' . wp_json_encode($data));
        }

        // PRIMEIRO: Enfileirar script tracker
        wp_enqueue_script(
            'iw8-wa-tracker',
            IW8_WA_CLICK_TRACKER_PLUGIN_URL . 'assets/js/tracker.js',
            [], // Sem dependências
            IW8_WA_CLICK_TRACKER_VERSION,
            true // No footer
        );

        // Ex.: dentro do método que enfileira o tracker
        $phone = get_option('iw8_wa_phone', '');

        // Monte os dados que o tracker.js vai ler
        $data = [
            'ajax_url' => admin_url('admin-ajax.php'),
            'action'   => 'iw8_wa_click',                 // a action do AJAX
            'nonce'    => wp_create_nonce('iw8_wa_click'), // *** action string do nonce ***
            'phone'    => $phone,
            'debug'    => defined('WP_DEBUG') && WP_DEBUG,
            'noBeacon' => true,
        ];

        // (opcional) log pra conferência
        if (function_exists('error_log')) {
            error_log('IW8_WA_CLICK_TRACKER DEBUG: Dados preparados: ' . wp_json_encode($data));
        }

        // Envie pro JS ANTES do tracker.js
        wp_add_inline_script(
            'iw8-wa-tracker',
            'window.iw8WaData = ' . wp_json_encode($data) . ';',
            'before'
        );

        // DEPOIS: Adicionar dados inline ANTES do script
        wp_add_inline_script(
            'iw8-wa-tracker',
            'window.iw8WaData = ' . wp_json_encode($data) . ';',
            'before'
        );

        if (function_exists('error_log')) {
            error_log('IW8_WA_CLICK_TRACKER DEBUG: Assets enfileirados com sucesso');
        }
    }

    /**
     * Enfileirar assets do admin
     *
     * @return void
     */
    public function enqueue_admin()
    {
        // TODO: Implementar enfileiramento de assets do admin
        // - admin.js
        // - admin.css
    }

    /**
     * Localizar dados para JavaScript do frontend
     *
     * @return void
     */
    public function localize_front_data()
    {
        // TODO: Implementar localização de dados
        // - nonce
        // - URLs
        // - configurações
    }

    /**
     * Verificar se telefone é válido
     *
     * @param string $phone Número de telefone
     * @return bool
     */
    private function isValidPhone($phone)
    {
        if (empty($phone)) {
            if (function_exists('error_log')) {
                error_log('IW8_WA_CLICK_TRACKER DEBUG: Telefone vazio');
            }
            return false;
        }

        // Normalizar e verificar comprimento
        $digits_only = preg_replace('/[^0-9]/', '', $phone);
        $is_valid = strlen($digits_only) >= 10 && strlen($digits_only) <= 15;

        if (function_exists('error_log')) {
            error_log('IW8_WA_CLICK_TRACKER DEBUG: Telefone original: ' . $phone . ', dígitos: ' . $digits_only . ', válido: ' . ($is_valid ? 'SIM' : 'NÃO'));
        }

        return $is_valid;
    }
}
