<?php

namespace IW8\WaClickTracker\Database;

if (!defined('ABSPATH')) {
    exit;
}

class TableClicks
{
    private $table_name;

    public function __construct()
    {
        global $wpdb;

        $new = $wpdb->prefix . 'iw8_wa_clicks';
        $old = $wpdb->prefix . 'wa_clicks';

        $existsNew = (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM information_schema.tables
             WHERE table_schema = DATABASE() AND table_name = %s",
                $new
            )
        );

        $this->table_name = $existsNew ? $new : $old;
    }

    /**
     * Cria/atualiza a estrutura da tabela (idempotente).
     * Usa dbDelta para aplicar diffs (inclui novos índices em upgrades).
     */
    public function ensure_schema(): bool
    {
        global $wpdb;
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE {$this->table_name} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            url varchar(255) NOT NULL,
            page_url text NULL,
            element_tag varchar(50) NULL,
            element_text text NULL,
            user_id bigint(20) unsigned NULL,
            user_agent varchar(255) NULL,
            clicked_at datetime NOT NULL,
            PRIMARY KEY  (id),
            KEY clicked_at (clicked_at)
        ) {$charset_collate};";

        dbDelta($sql);

        // Fallback ultra-seguro: garante índice se por algum motivo o dbDelta não aplicou
        $this->ensure_indexes_fallback();

        return $this->table_exists();
    }

    /** Compat: mantém create_table() chamando ensure_schema() */
    public function create_table(): bool
    {
        return $this->ensure_schema();
    }

    /**
     * Garantir a tabela/estrutura (sempre roda dbDelta para upgrades).
     */
    public function ensure_table(): bool
    {
        return $this->ensure_schema();
    }

    private function table_exists(): bool
    {
        global $wpdb;
        $result = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $this->table_name));
        return $result === $this->table_name;
    }

    /**
     * Fallback: cria o índice se não existir (para instalações antigas).
     */
    private function ensure_indexes_fallback(): void
    {
        global $wpdb;

        // Checa se já existe um índice em clicked_at
        $exists = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(1)
                 FROM INFORMATION_SCHEMA.STATISTICS
                 WHERE TABLE_SCHEMA = %s AND TABLE_NAME = %s AND COLUMN_NAME = 'clicked_at'",
                DB_NAME,
                $this->table_name
            )
        );

        if (!$exists) {
            // Nome do índice pode ser qualquer; mantemos 'clicked_at' por consistência
            $wpdb->query("CREATE INDEX clicked_at ON {$this->table_name} (clicked_at)");
        }
    }

    public function get_table_name(): string
    {
        return $this->table_name;
    }
}
