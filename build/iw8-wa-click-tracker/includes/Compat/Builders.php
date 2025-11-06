<?php
/**
 * Classe para compatibilidade com page builders
 *
 * @package IW8_WaClickTracker\Compat
 * @version 1.3.0
 */

namespace IW8\WaClickTracker\Compat;

// Prevenir acesso direto
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Classe Builders
 */
class Builders
{
    /**
     * Construtor da classe
     */
    public function __construct()
    {
        // TODO: Implementar inicialização
    }

    /**
     * Verificar compatibilidade com page builders
     *
     * @return array
     */
    public function check_compatibility()
    {
        // TODO: Implementar verificação de compatibilidade
        // - Elementor
        // - WPBakery
        // - Beaver Builder
        // - Gutenberg
        return [];
    }
}
