<?php
/**
 * Autoloader simples para o plugin IW8 – Rastreador de Cliques WhatsApp
 * Implementa PSR-4 para o namespace base IW8\WaClickTracker
 *
 * @package IW8_WaClickTracker
 * @version 1.3.0
 */

// Prevenir acesso direto
if (!defined('ABSPATH')) {
    exit;
}

// Registrar autoloader
spl_autoload_register(function ($class) {
    // Verificar se a classe pertence ao nosso namespace
    $prefix = 'IW8\\WaClickTracker\\';
    $base_dir = IW8_WA_CLICK_TRACKER_PLUGIN_DIR . 'includes/';
    
    // Se não pertence ao nosso namespace, ignorar
    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }
    
    // Remover o namespace base da classe
    $relative_class = substr($class, $len);
    
    // Converter namespace em caminho de arquivo
    $file = $base_dir . str_replace('\\', '/', $relative_class) . '.php';
    
    // Se o arquivo existir, carregá-lo
    if (file_exists($file)) {
        require_once $file;
    }
});
