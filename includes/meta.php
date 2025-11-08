<?php
/**
 * Helpers de metadados de clique (city/region/country etc.)
 *
 * @package IW8_WaClickTracker\Includes
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Nome completo da tabela de meta.
 */
function iw8_wa_meta_table_name(): string {
    global $wpdb;
    return $wpdb->prefix . 'iw8_wa_click_meta';
}

/**
 * Garante a existência da tabela de meta (operação idempotente e barata).
 * Evita depender de hooks de ativação por enquanto.
 */
function iw8_wa_ensure_meta_table(): void {
    global $wpdb;

    $table   = iw8_wa_meta_table_name();
    $charset = $wpdb->get_charset_collate();

    // Usamos CREATE TABLE IF NOT EXISTS para ser leve — sem dbDelta neste passo.
    $sql = "CREATE TABLE IF NOT EXISTS `{$table}` (
        `meta_id`   BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        `click_id`  BIGINT UNSIGNED NOT NULL,
        `meta_key`  VARCHAR(64)     NOT NULL,
        `meta_value` LONGTEXT       NULL,
        PRIMARY KEY (`meta_id`),
        KEY `idx_click` (`click_id`),
        KEY `idx_key` (`meta_key`)
    ) {$charset};";

    // Suprime warnings se a tabela já existir.
    $wpdb->query($sql);
}

/**
 * Adiciona um metadado (key–value) a um clique.
 *
 * @return bool true em caso de sucesso
 */
function iw8_wa_add_click_meta(int $click_id, string $key, $value): bool {
    global $wpdb;

    if ($click_id <= 0) return false;
    $key = trim($key);
    if ($key === '') return false;
    if (strlen($key) > 64) {
        $key = substr($key, 0, 64);
    }

    iw8_wa_ensure_meta_table();

    $table = iw8_wa_meta_table_name();
    $data  = [
        'click_id'   => $click_id,
        'meta_key'   => $key,
        'meta_value' => maybe_serialize($value),
    ];

    // phpcs:ignore WordPress.DB.DirectDatabaseQuery
    $ok = $wpdb->insert($table, $data, ['%d', '%s', '%s']);
    return $ok !== false;
}

/**
 * Lê metadados de um clique. Se $keys vier vazio, retorna todos.
 *
 * @return array<string,mixed> [meta_key => meta_value]
 */
function iw8_wa_get_click_meta(int $click_id, array $keys = []): array {
    global $wpdb;

    if ($click_id <= 0) return [];

    $table = iw8_wa_meta_table_name();

    if (!empty($keys)) {
        // Previne chaves vazias e limita tamanho.
        $keys = array_values(array_filter(array_map(function($k){
            $k = trim((string)$k);
            if ($k === '') return null;
            return strlen($k) > 64 ? substr($k, 0, 64) : $k;
        }, $keys)));

        if (empty($keys)) return [];

        $placeholders = implode(',', array_fill(0, count($keys), '%s'));
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery
        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT meta_key, meta_value FROM `{$table}` WHERE click_id = %d AND meta_key IN ($placeholders)",
                array_merge([$click_id], $keys)
            ),
            ARRAY_A
        );
    } else {
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL
        $rows = $wpdb->get_results(
            $wpdb->prepare("SELECT meta_key, meta_value FROM `{$table}` WHERE click_id = %d", $click_id),
            ARRAY_A
        );
    }

    $out = [];
    foreach ((array)$rows as $r) {
        $out[(string)$r['meta_key']] = maybe_unserialize($r['meta_value']);
    }
    return $out;
}
