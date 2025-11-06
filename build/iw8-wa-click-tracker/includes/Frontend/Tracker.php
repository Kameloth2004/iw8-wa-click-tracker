<?php
/**
 * Classe para rastreamento de cliques no frontend
 *
 * @package IW8_WaClickTracker\Frontend
 * @version 1.3.0
 */

namespace IW8\WaClickTracker\Frontend;

// Prevenir acesso direto
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Classe Tracker
 */
class Tracker
{
    /**
     * Instância da classe Assets
     *
     * @var \IW8\WaClickTracker\Core\Assets
     */
    private $assets;

    /**
     * Construtor da classe
     */
    public function __construct()
    {
        $this->assets = new \IW8\WaClickTracker\Core\Assets();
    }

    /**
     * Verificar se deve enfileirar o tracker JavaScript
     *
     * @return void
     */
    public function maybe_enqueue_tracker_js()
    {
        // Verificar se não é admin
        if (is_admin()) {
            return;
        }

        // Verificar se telefone está configurado e é válido
        $phone = get_option('iw8_wa_phone', '');
        if (!$this->isValidPhone($phone)) {
            return;
        }

        // Enfileirar assets do frontend
        $this->assets->enqueue_front();
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
            return false;
        }

        // Normalizar e verificar comprimento
        $digits_only = preg_replace('/[^0-9]/', '', $phone);
        return strlen($digits_only) >= 10 && strlen($digits_only) <= 15;
    }
}
