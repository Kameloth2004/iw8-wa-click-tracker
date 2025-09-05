<?php

declare(strict_types=1);

namespace IW8\WaClickTracker\Export;

if (!defined('ABSPATH')) {
    exit;
}

final class CsvExporter
{
    /**
     * Emite o CSV diretamente no output com headers de download.
     * Espera filtros (já saneados pelo handler) nas chaves:
     *  - date_from (YYYY-MM-DD)
     *  - date_to   (YYYY-MM-DD)
     *  - page_url  (prefix match)
     *  - element_tag
     *  - user_id
     *  - (opcionais) limit, order
     */
    public static function outputCsv(array $filters): void
    {
        \nocache_headers();

        $filename = 'iw8-wa-clicks-' . gmdate('Ymd-His') . '.csv';
        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('X-Content-Type-Options: nosniff');
        header('Pragma: no-cache');
        header('Expires: 0');

        // BOM para Excel PT-BR reconhecer UTF-8
        echo "\xEF\xBB\xBF";

        global $wpdb;
        /** @var \wpdb $wpdb */
        $new = $wpdb->prefix . 'iw8_wa_clicks';
        $old = $wpdb->prefix . 'wa_clicks';

        // Prefere a tabela nova; se não existir, cai na legada
        $existsNew = (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM information_schema.tables
         WHERE table_schema = DATABASE() AND table_name = %s",
                $new
            )
        );
        $table = $existsNew ? $new : $old;

        // Coluna de data real presente na tabela
        $dtCol = $existsNew ? 'clicked_at' : 'created_at';

        // Colunas exportadas (ordem fixa no CSV)
        $columns = [
            'id',
            'url',
            'page_url',
            'element_tag',
            'element_text',
            'user_agent',
            'clicked_at', // sempre presente na saída (alias no legado)
        ];

        // Cabeçalhos do CSV (rótulos)
        $headers = [
            'ID',
            'URL',
            'Página',
            'Elemento (tag)',
            'Elemento (texto)',
            'User-Agent',
            'Data/Hora',
        ];

        // WHERE + parâmetros
        $where  = 'WHERE 1=1';
        $params = [];

        // Filtros
        $dateFrom = isset($filters['date_from']) ? (string)$filters['date_from'] : '';
        $dateTo   = isset($filters['date_to'])   ? (string)$filters['date_to']   : '';
        $pageUrl  = isset($filters['page_url'])  ? (string)$filters['page_url']  : '';
        $tag      = isset($filters['element_tag']) ? (string)$filters['element_tag'] : '';
        $userId   = isset($filters['user_id'])   ? (int)$filters['user_id']      : 0;

        if ($dateFrom !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateFrom)) {
            $where   .= " AND `{$dtCol}` >= %s";
            $params[] = $dateFrom . ' 00:00:00';
        }
        if ($dateTo !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateTo)) {
            $where   .= " AND `{$dtCol}` <= %s";
            $params[] = $dateTo . ' 23:59:59';
        }
        if ($pageUrl !== '') {
            $where   .= ' AND `page_url` LIKE %s';
            $params[] = $wpdb->esc_like($pageUrl) . '%';
        }
        if ($tag !== '') {
            $where   .= ' AND `element_tag` = %s';
            $params[] = $tag;
        }
        if ($userId > 0) {
            $where   .= ' AND `user_id` = %d';
            $params[] = $userId;
        }

        // Limite seguro
        $limit = isset($filters['limit']) ? (int)$filters['limit'] : 1000;
        if ($limit < 1)   $limit = 1;
        if ($limit > 5000) $limit = 5000;

        $order = (isset($filters['order']) && strtolower((string)$filters['order']) === 'asc') ? 'ASC' : 'DESC';

        // SELECT com alias para clicked_at quando necessário
        $clickedSelect = $existsNew ? "`clicked_at`" : "`created_at` AS `clicked_at`";

        // Monta a lista de colunas reais (mapeando clicked_at para o alias acima)
        $selectPieces = [
            '`id`',
            '`url`',
            '`page_url`',
            '`element_tag`',
            '`element_text`',
            '`user_agent`',
            $clickedSelect,
        ];
        $select = implode(', ', $selectPieces);

        // Consulta única que gera o CSV
        $sql = "
    SELECT {$select}
    FROM `{$table}`
    {$where}
    ORDER BY `{$dtCol}` {$order}, `id` {$order}
    LIMIT %d
";
        $params[] = $limit;

        $rows = $wpdb->get_results($wpdb->prepare($sql, $params), ARRAY_A);

        $out = fopen('php://output', 'w');
        if ($out === false) {
            \wp_die(\esc_html__('Falha ao abrir saída do CSV.', 'iw8-wa-click-tracker'), '', ['response' => 500]);
        }

        // Delimitador ';' para compatibilidade com Excel PT-BR
        $delimiter = ';';

        // Linha de cabeçalho
        fputcsv($out, $headers, $delimiter);

        // Linhas
        foreach ($rows as $row) {
            $line = [];
            foreach ($columns as $col) {
                $line[] = isset($row[$col]) ? (string)$row[$col] : '';
            }
            fputcsv($out, $line, $delimiter);
        }

        fclose($out);
        exit;
    }
}
