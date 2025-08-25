<?php
/**
 * Arquivo de desinstalação do plugin IW8 – Rastreador de Cliques WhatsApp
 *
 * @package IW8_WaClickTracker
 * @version 1.3.0
 */

// Prevenir acesso direto
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

// Verificar se o usuário tem permissões adequadas
if (!current_user_can('activate_plugins')) {
    return;
}

// TODO: Implementar lógica de desinstalação
// Por padrão, NÃO remover tabelas do banco (configurável)
// - Remover opções do banco
// - Remover metadados
// - Limpar cache/transients

// Exemplo de limpeza de opções (descomentar quando implementar)
/*
$options_to_remove = [
    'iw8_wa_click_tracker_version',
    'iw8_wa_click_tracker_settings',
    'iw8_wa_click_tracker_db_version'
];

foreach ($options_to_remove as $option) {
    delete_option($option);
}

// Limpar opções de rede se for plugin de rede
if (is_multisite()) {
    foreach ($options_to_remove as $option) {
        delete_site_option($option);
    }
}
*/
