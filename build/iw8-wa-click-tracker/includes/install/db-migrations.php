<?php
// Compatível com PHP 7.x
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Executa as migrações necessárias para o IW8 – WA Click Tracker.
 * - Cria/ajusta a tabela {prefix}iw8_wa_clicks
 * - Atualiza a option 'iw8_wa_db_version'
 * - (Opcional) insere dados de teste se IW8_WA_SEED_DEV = true
 */
function iw8_wa_run_migrations()
{
    global $wpdb;
    $table = $wpdb->prefix . 'iw8_wa_clicks';
    $charset_collate = $wpdb->get_charset_collate();

    // Certifique-se de carregar dbDelta
    require_once ABSPATH . 'wp-admin/includes/upgrade.php';

    // Tabela principal de cliques (UTC em clicked_at)
    $sql = "CREATE TABLE {$table} (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        clicked_at DATETIME NOT NULL,
        url TEXT NULL,
        page_url TEXT NULL,
        element_tag VARCHAR(20) NULL,
        element_text VARCHAR(255) NULL,
        user_agent TEXT NULL,
        user_id BIGINT NULL,
        geo_city VARCHAR(64) NULL,
        geo_region VARCHAR(16) NULL,
        PRIMARY KEY  (id),
        KEY clicked_at_id (clicked_at, id)
    ) {$charset_collate};";

    dbDelta($sql);

    // Atualiza versão da migração
    update_option('iw8_wa_db_version', IW8_WA_DB_VERSION);

    // Seed opcional de desenvolvimento
    if (defined('IW8_WA_SEED_DEV') && IW8_WA_SEED_DEV) {
        // Evita duplicar seeds: se já há algo na faixa, não insere
        $exists = (int)$wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$table} WHERE clicked_at BETWEEN %s AND %s",
                '2025-08-20 00:00:00',
                '2025-08-28 23:59:59'
            )
        );

        if ($exists === 0) {
            $rows = array(
                array(
                    'clicked_at'   => '2025-08-20 10:00:00',
                    'url'          => 'https://wa.me/5511999999999?text=Produto+A',
                    'page_url'     => 'https://exemplo.local/produto-a',
                    'element_tag'  => 'a',
                    'element_text' => 'Fale no WhatsApp',
                    'user_agent'   => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)',
                    'user_id'      => null,
                    'geo_city'     => 'São Paulo',
                    'geo_region'   => 'SP',
                ),
                array(
                    'clicked_at'   => '2025-08-22 15:30:00',
                    'url'          => 'https://wa.me/5511988888888?text=Produto+B',
                    'page_url'     => 'https://exemplo.local/produto-b',
                    'element_tag'  => 'a',
                    'element_text' => 'Chamar no WhatsApp',
                    'user_agent'   => 'Mozilla/5.0 (X11; Linux x86_64)',
                    'user_id'      => 42,
                    'geo_city'     => 'Rio de Janeiro',
                    'geo_region'   => 'RJ',
                ),
            );

            foreach ($rows as $r) {
                $wpdb->insert($table, $r, array(
                    '%s',
                    '%s',
                    '%s',
                    '%s',
                    '%s',
                    '%s',
                    '%s',
                    '%d',
                    '%s',
                    '%s'
                ));
            }
        }
    }
}
