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
        $table = $wpdb->prefix . 'wa_clicks';

        // Colunas exportadas (ordem fixa)
        $columns = [
            'id',
            'url',
            'page_url',
            'element_tag',
            'element_text',
            'user_agent',
            'clicked_at',
        ];

        // Cabeçalhos do CSV (altere rótulos se quiser)
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

        $dateFrom = isset($filters['date_from']) ? (string)$filters['date_from'] : '';
        $dateTo   = isset($filters['date_to'])   ? (string)$filters['date_to']   : '';
        $pageUrl  = isset($filters['page_url'])  ? (string)$filters['page_url']  : '';
        $tag      = isset($filters['element_tag']) ? (string)$filters['element_tag'] : '';
        $userId   = isset($filters['user_id'])   ? (int)$filters['user_id']      : 0;

        if ($dateFrom !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateFrom)) {
            $where   .= ' AND clicked_at >= %s';
            $params[] = $dateFrom . ' 00:00:00';
        }
        if ($dateTo !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateTo)) {
            $where   .= ' AND clicked_at <= %s';
            $params[] = $dateTo . ' 23:59:59';
        }
        if ($pageUrl !== '') {
            $where   .= ' AND page_url LIKE %s';
            $params[] = $wpdb->esc_like($pageUrl) . '%';
        }
        if ($tag !== '') {
            $where   .= ' AND element_tag = %s';
            $params[] = $tag;
        }
        if ($userId > 0) {
            $where   .= ' AND user_id = %d';
            $params[] = $userId;
        }

        $limit = isset($filters['limit']) ? (int)$filters['limit'] : 1000;
        if ($limit < 1) {
            $limit = 1;
        }
        if ($limit > 5000) {
            $limit = 5000;
        }

        $order = (isset($filters['order']) && strtolower((string)$filters['order']) === 'asc') ? 'ASC' : 'DESC';

        $select = implode(',', array_map(static fn(string $c) => "`$c`", $columns));
        $sql    = "SELECT {$select} FROM `{$table}` {$where} ORDER BY clicked_at {$order} LIMIT %d";
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
