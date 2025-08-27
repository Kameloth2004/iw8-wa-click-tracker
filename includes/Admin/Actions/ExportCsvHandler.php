<?php

declare(strict_types=1);

namespace IW8\WaClickTracker\Admin\Actions;

use IW8\WaClickTracker\Export\CsvExporter;

if (!defined('ABSPATH')) {
    exit;
}

final class ExportCsvHandler
{
    private const ACTION = 'iw8_wa_export_csv';   // action do admin-post
    private const NONCE  = 'iw8_wa_export';       // nonce exigido

    public static function register(): void
    {
        // Somente usuários logados
        add_action('admin_post_' . self::ACTION, [self::class, 'handle']);
        // Defesa extra contra chamadas de não-logados
        add_action('admin_post_nopriv_' . self::ACTION, [self::class, 'deny']);
    }

    public static function deny(): void
    {
        wp_die(
            esc_html__('Acesso negado.', 'iw8-wa-click-tracker'),
            esc_html__('Permissão insuficiente', 'iw8-wa-click-tracker'),
            ['response' => 403]
        );
    }

    public static function handle(): void
    {
        // 1) Permissão mínima
        if (! current_user_can('manage_options')) {
            wp_die(
                esc_html__('Você não tem permissão para exportar.', 'iw8-wa-click-tracker'),
                esc_html__('Permissão insuficiente', 'iw8-wa-click-tracker'),
                ['response' => 403]
            );
        }

        // 2) Nonce (mantendo o seu: 'iw8_wa_export')
        check_admin_referer(self::NONCE);

        // 3) Anti-cache (antes de qualquer saída)
        nocache_headers();

        // 4) Coleta e saneamento dos filtros (POST)
        $filters = self::buildFiltersFromRequest($_POST);

        // 5) Disparo da exportação
        // IMPORTANTE: CsvExporter::outputCsv($filters) deve emitir headers e CSV (com BOM se desejar)
        try {
            CsvExporter::outputCsv($filters);
        } catch (\Throwable $e) {
            status_header(500);
            wp_die(
                sprintf(
                    /* translators: %s error message */
                    esc_html__('Falha ao exportar CSV: %s', 'iw8-wa-click-tracker'),
                    esc_html($e->getMessage())
                ),
                esc_html__('Erro na exportação', 'iw8-wa-click-tracker'),
                ['response' => 500]
            );
        }

        exit;
    }

    /**
     * Saneamento centralizado de filtros vindos do formulário.
     * Ajuste chaves conforme o CsvExporter espera (date_from, date_to, page_url, element_tag, user_id).
     */
    private static function buildFiltersFromRequest(array $src): array
    {
        $src = wp_unslash($src);

        $dateFrom = isset($src['date_from']) ? sanitize_text_field($src['date_from']) : '';
        $dateTo   = isset($src['date_to'])   ? sanitize_text_field($src['date_to'])   : '';
        $pageUrl  = isset($src['page_url'])  ? esc_url_raw($src['page_url'])          : '';
        $tag      = isset($src['element_tag']) ? sanitize_key($src['element_tag'])    : '';
        $userId   = isset($src['user_id'])   ? (int) $src['user_id']                   : 0;

        // Normaliza datas para YYYY-MM-DD
        $dateFrom = self::normalizeDate($dateFrom);
        $dateTo   = self::normalizeDate($dateTo);

        // Restringe tag a caracteres seguros
        if ($tag && ! preg_match('/^[a-z0-9\-_]+$/i', $tag)) {
            $tag = '';
        }

        $filters = [
            'date_from'   => $dateFrom ?: null,
            'date_to'     => $dateTo   ?: null,
            'page_url'    => $pageUrl  ?: null,
            'element_tag' => $tag      ?: null,
            'user_id'     => $userId   ?: null,
        ];

        return array_filter($filters, static fn($v) => null !== $v && '' !== $v);
    }

    private static function normalizeDate(string $raw): string
    {
        $raw = trim($raw);
        if ($raw === '') return '';

        $d1 = \DateTime::createFromFormat('Y-m-d', $raw);
        if ($d1 && $d1->format('Y-m-d') === $raw) {
            return $raw;
        }

        $d2 = \DateTime::createFromFormat('d/m/Y', $raw);
        if ($d2) {
            return $d2->format('Y-m-d');
        }

        return '';
    }
}
