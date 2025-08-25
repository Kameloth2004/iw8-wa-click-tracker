<?php
/**
 * Classe para exportação de dados em CSV
 *
 * @package IW8_WaClickTracker\Export
 * @version 1.3.0
 */

namespace IW8\WaClickTracker\Export;

// Prevenir acesso direto
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Classe CsvExporter
 */
class CsvExporter
{
    /**
     * Instância do repositório de cliques
     *
     * @var \IW8\WaClickTracker\Database\ClickRepository
     */
    private $repository;

    /**
     * Construtor da classe
     */
    public function __construct()
    {
        $this->repository = new \IW8\WaClickTracker\Database\ClickRepository();
    }

    /**
     * Exportar dados em CSV
     *
     * @param array $filter Filtros para aplicar na exportação
     * @return void
     */
    public function outputCsv($filter)
    {
        // Verificar permissões
        if (!current_user_can('manage_options')) {
            wp_die(__('Você não tem permissão para exportar dados.', 'iw8-wa-click-tracker'));
        }

        try {
            // Verificar se telefone está configurado
            if (!\IW8\WaClickTracker\Utils\Helpers::isPhoneConfigured()) {
                wp_die(__('Não é possível exportar: telefone não configurado.', 'iw8-wa-click-tracker'));
            }

            // Gerar nome do arquivo
            $filename = 'wa-cliques-' . date('Ymd-His') . '.csv';

            // Configurar headers HTTP
            $this->setHeaders($filename);

            // Abrir output stream
            $output = fopen('php://output', 'w');
            if ($output === false) {
                throw new \Exception('Não foi possível abrir stream de saída');
            }

            // Escrever BOM UTF-8
            fwrite($output, "\xEF\xBB\xBF");

            // Escrever cabeçalho
            $headers = [
                'id',
                'clicked_at',
                'url',
                'page_url',
                'element_tag',
                'element_text',
                'user_id',
                'user_agent'
            ];
            fputcsv($output, $headers);

            // Streaming em lotes
            $batch = 500;
            $offset = 0;
            $total_exported = 0;

            do {
                // Obter lote de dados
                $clicks = $this->repository->list($filter, [
                    'per_page' => $batch,
                    'offset' => $offset
                ]);

                if (empty($clicks)) {
                    break;
                }

                // Processar cada clique
                foreach ($clicks as $click) {
                    $row = [
                        $click->id,
                        $click->clicked_at,
                        $click->url,
                        $click->page_url ?: '',
                        $click->element_tag ?: '',
                        $click->element_text ?: '',
                        $click->user_id ?: '',
                        $click->user_agent ?: ''
                    ];

                    fputcsv($output, $row);
                    $total_exported++;
                }

                // Incrementar offset
                $offset += $batch;

                // Flush para evitar timeout
                if (function_exists('fflush')) {
                    fflush($output);
                }

            } while (count($clicks) === $batch);

            // Fechar stream
            fclose($output);

            // Log da exportação (opcional)
            if (function_exists('error_log')) {
                error_log("IW8 WaClickTracker: CSV exportado com sucesso. Total: {$total_exported} registros");
            }

            // Finalizar script
            exit;

        } catch (\Exception $e) {
            // Log do erro
            if (function_exists('error_log')) {
                error_log('IW8 WaClickTracker CSV Export Error: ' . $e->getMessage());
            }

            // Retornar erro HTTP 500
            http_response_code(500);
            wp_die(__('Erro ao exportar dados. Tente novamente.', 'iw8-wa-click-tracker'));
        }
    }

    /**
     * Configurar headers HTTP para download do CSV
     *
     * @param string $filename Nome do arquivo
     * @return void
     */
    private function setHeaders($filename)
    {
        // Prevenir qualquer saída antes dos headers
        if (headers_sent()) {
            throw new \Exception('Headers já foram enviados');
        }

        // Headers para download
        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        header('Pragma: no-cache');
        header('Expires: 0');

        // Prevenir cache do navegador
        nocache_headers();
    }

    /**
     * Validar e preparar filtros para exportação
     *
     * @param array $raw_filters Filtros brutos da requisição
     * @return array Filtros validados
     */
    public function prepareFilters($raw_filters)
    {
        $filters = [];

        // Busca
        if (!empty($raw_filters['s'])) {
            $filters['s'] = sanitize_text_field($raw_filters['s']);
        }

        // Data de início
        if (!empty($raw_filters['from'])) {
            $date = sanitize_text_field($raw_filters['from']);
            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
                $filters['from'] = $date;
            }
        }

        // Data de fim
        if (!empty($raw_filters['to'])) {
            $date = sanitize_text_field($raw_filters['to']);
            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
                $filters['to'] = $date;
            }
        }

        // Regex de URL baseado no telefone configurado
        $phone = \IW8\WaClickTracker\Utils\Helpers::getConfiguredPhone();
        if ($phone) {
            $filters['url_regexp'] = \IW8\WaClickTracker\Utils\Helpers::generateUrlRegexp($phone);
        }

        return $filters;
    }
}
