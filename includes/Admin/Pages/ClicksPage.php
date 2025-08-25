<?php

/**
 * Página de relatórios de cliques
 *
 * @package IW8_WaClickTracker\Admin\Pages
 * @version 1.3.0
 */

namespace IW8\WaClickTracker\Admin\Pages;

// Prevenir acesso direto
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Classe ClicksPage
 */
class ClicksPage
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
     * Renderizar a página
     *
     * @return void
     */
    public function render()
    {
        // Verificar permissões
        if (!current_user_can('manage_options')) {
            wp_die(__('Você não tem permissão para acessar esta página.', 'iw8-wa-click-tracker'));
        }

        // Verificar se telefone está configurado
        if (!\IW8\WaClickTracker\Utils\Helpers::isPhoneConfigured()) {
            $this->render_no_phone_notice();
            return;
        }

        // Obter filtros
        $filters = $this->get_filters();

        // Obter paginação
        $pagination = $this->get_pagination();

        // Obter dados
        $clicks = $this->repository->list($filters, $pagination);
        $totals = $this->repository->countTotals($filters);
        $totals = wp_parse_args((array) $totals, ['total' => 0, 'last7' => 0, 'last30' => 0]);

?>
        <div class="wrap">
            <h1><?php _e('Relatórios - WA Cliques', 'iw8-wa-click-tracker'); ?></h1>

            <?php $this->render_metrics($totals); ?>

            <?php $this->render_filters($filters); ?>

            <?php $this->render_clicks_table($clicks, $totals, $pagination); ?>
        </div>
    <?php
    }

    /**
     * Renderizar notice de telefone não configurado
     *
     * @return void
     */
    private function render_no_phone_notice()
    {
    ?>
        <div class="wrap">
            <h1><?php _e('Relatórios - WA Cliques', 'iw8-wa-click-tracker'); ?></h1>

            <div class="notice notice-warning">
                <p>
                    <strong><?php _e('Atenção:', 'iw8-wa-click-tracker'); ?></strong>
                    <?php _e('O telefone não está configurado. Configure um telefone válido em', 'iw8-wa-click-tracker'); ?>
                    <a href="<?php echo admin_url('admin.php?page=iw8-wa-clicks-settings'); ?>">
                        <?php _e('Configurações', 'iw8-wa-click-tracker'); ?>
                    </a>
                    <?php _e('para visualizar os relatórios.', 'iw8-wa-click-tracker'); ?>
                </p>
            </div>
        </div>
    <?php
    }

    /**
     * Renderizar métricas
     *
     * @param array $totals
     * @return void
     */
    private function render_metrics($totals)
    {
    ?>
        <div class="iw8-metrics">
            <div class="iw8-metric-card">
                <h3><?php _e('Total (Filtro Atual)', 'iw8-wa-click-tracker'); ?></h3>
                <div class="iw8-metric-value"><?php echo number_format($totals['total']); ?></div>
            </div>

            <div class="iw8-metric-card">
                <h3><?php _e('Últimos 7 Dias', 'iw8-wa-click-tracker'); ?></h3>
                <div class="iw8-metric-value"><?php echo number_format($totals['last7']); ?></div>
            </div>

            <div class="iw8-metric-card">
                <h3><?php _e('Últimos 30 Dias', 'iw8-wa-click-tracker'); ?></h3>
                <div class="iw8-metric-value"><?php echo number_format($totals['last30']); ?></div>
            </div>
        </div>

        <style>
            .iw8-metrics {
                display: flex;
                gap: 20px;
                margin: 20px 0;
            }

            .iw8-metric-card {
                background: #fff;
                border: 1px solid #ccd0d4;
                border-radius: 4px;
                padding: 20px;
                text-align: center;
                min-width: 150px;
            }

            .iw8-metric-card h3 {
                margin: 0 0 10px 0;
                font-size: 14px;
                color: #646970;
            }

            .iw8-metric-value {
                font-size: 24px;
                font-weight: bold;
                color: #2271b1;
            }
        </style>
    <?php
    }

    /**
     * Renderizar filtros
     *
     * @param array $filters
     * @return void
     */
    private function render_filters($filters)
    {
    ?>
        <div class="iw8-filters">
            <form method="get" action="">
                <input type="hidden" name="page" value="iw8-wa-clicks" />

                <div class="iw8-filter-row">
                    <label for="s"><?php _e('Buscar:', 'iw8-wa-click-tracker'); ?></label>
                    <input type="text"
                        id="s"
                        name="s"
                        value="<?php echo esc_attr($filters['s'] ?? ''); ?>"
                        placeholder="<?php _e('Buscar em página de origem...', 'iw8-wa-click-tracker'); ?>" />

                    <label for="from"><?php _e('De:', 'iw8-wa-click-tracker'); ?></label>
                    <input type="date"
                        id="from"
                        name="from"
                        value="<?php echo esc_attr($filters['from'] ?? ''); ?>" />

                    <label for="to"><?php _e('Até:', 'iw8-wa-click-tracker'); ?></label>
                    <input type="date"
                        id="to"
                        name="to"
                        value="<?php echo esc_attr($filters['to'] ?? ''); ?>" />

                    <input type="submit"
                        class="button button-primary"
                        value="<?php _e('Filtrar', 'iw8-wa-click-tracker'); ?>" />

                    <a href="<?php echo admin_url('admin.php?page=iw8-wa-clicks'); ?>"
                        class="button">
                        <?php _e('Limpar', 'iw8-wa-click-tracker'); ?>
                    </a>

                    <?php if (\IW8\WaClickTracker\Utils\Helpers::isPhoneConfigured()): ?>
                        <a href="<?php echo $this->get_export_url($filters); ?>"
                            class="button button-secondary">
                            <?php _e('Exportar CSV', 'iw8-wa-click-tracker'); ?>
                        </a>
                    <?php else: ?>
                        <button type="button"
                            class="button button-secondary"
                            disabled
                            title="<?php _e('Configure o telefone em Configurações', 'iw8-wa-click-tracker'); ?>">
                            <?php _e('Exportar CSV', 'iw8-wa-click-tracker'); ?>
                        </button>
                    <?php endif; ?>
                </div>
            </form>
        </div>

        <style>
            .iw8-filters {
                background: #fff;
                border: 1px solid #ccd0d4;
                border-radius: 4px;
                padding: 20px;
                margin: 20px 0;
            }

            .iw8-filter-row {
                display: flex;
                align-items: center;
                gap: 10px;
                flex-wrap: wrap;
            }

            .iw8-filter-row label {
                font-weight: 500;
                margin-right: 5px;
            }

            .iw8-filter-row input[type="text"],
            .iw8-filter-row input[type="date"] {
                margin-right: 15px;
            }
        </style>
        <?php
    }

    /**
     * Renderizar tabela de cliques
     *
     * @param array $clicks
     * @param array $totals
     * @param array $pagination
     * @return void
     */
    private function render_clicks_table($clicks, $totals, $pagination)
    {
        if (empty($clicks)) {
        ?>
            <div class="notice notice-info">
                <p><?php _e('Nenhum clique encontrado com os filtros aplicados.', 'iw8-wa-click-tracker'); ?></p>
            </div>
        <?php
            return;
        }

        ?>
        <div class="iw8-clicks-table">
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th><?php _e('Data/Hora', 'iw8-wa-click-tracker'); ?></th>
                        <th><?php _e('Página (Origem)', 'iw8-wa-click-tracker'); ?></th>
                        <th><?php _e('Elemento', 'iw8-wa-click-tracker'); ?></th>
                        <th><?php _e('Texto Visível', 'iw8-wa-click-tracker'); ?></th>
                        <th><?php _e('Usuário', 'iw8-wa-click-tracker'); ?></th>
                        <th><?php _e('User-Agent', 'iw8-wa-click-tracker'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($clicks as $click): ?>
                        <tr>
                            <td><?php echo \IW8\WaClickTracker\Utils\Helpers::format_datetime($click->clicked_at); ?></td>
                            <td>
                                <?php if (!empty($click->page_url)): ?>
                                    <a href="<?php echo esc_url($click->page_url); ?>"
                                        target="_blank"
                                        rel="noopener">
                                        <?php echo esc_html(wp_trim_words($click->page_url, 8, '...')); ?>
                                    </a>
                                <?php else: ?>
                                    -
                                <?php endif; ?>
                            </td>
                            <td><?php echo esc_html($click->element_tag ?: '-'); ?></td>
                            <td><?php echo esc_html(\IW8\WaClickTracker\Utils\Helpers::trim_text($click->element_text, 20)); ?></td>
                            <td>
                                <?php if ($click->user_id): ?>
                                    #<?php echo esc_html($click->user_id); ?>
                                <?php else: ?>
                                    -
                                <?php endif; ?>
                            </td>
                            <td title="<?php echo esc_attr($click->user_agent ?: ''); ?>">
                                <?php echo esc_html(\IW8\WaClickTracker\Utils\Helpers::trim_text($click->user_agent, 8)); ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <?php $this->render_pagination($totals['total'], $pagination); ?>
        </div>
<?php
    }

    /**
     * Renderizar paginação
     *
     * @param int $total_items
     * @param array $pagination
     * @return void
     */
    private function render_pagination($total_items, $pagination)
    {
        if ($total_items <= $pagination['per_page']) {
            return;
        }

        $total_pages = ceil($total_items / $pagination['per_page']);
        $current_page = $pagination['paged'];

        echo '<div class="tablenav-pages">';
        echo paginate_links([
            'base' => add_query_arg('paged', '%#%'),
            'format' => '',
            'prev_text' => __('&laquo;'),
            'next_text' => __('&raquo;'),
            'total' => $total_pages,
            'current' => $current_page,
            'type' => 'plain'
        ]);
        echo '</div>';
    }

    /**
     * Obter filtros da requisição
     *
     * @return array
     */
    private function get_filters()
    {
        $filters = [];

        // Busca
        if (!empty($_GET['s'])) {
            $filters['s'] = sanitize_text_field($_GET['s']);
        }

        // Data de início
        if (!empty($_GET['from'])) {
            $filters['from'] = sanitize_text_field($_GET['from']);
        }

        // Data de fim
        if (!empty($_GET['to'])) {
            $filters['to'] = sanitize_text_field($_GET['to']);
        }

        // Regex de URL baseado no telefone
        $phone = \IW8\WaClickTracker\Utils\Helpers::getConfiguredPhone();
        if ($phone) {
            $filters['url_regexp'] = \IW8\WaClickTracker\Utils\Helpers::generateUrlRegexp($phone);
        }

        return $filters;
    }

    /**
     * Obter parâmetros de paginação
     *
     * @return array
     */
    private function get_pagination()
    {
        $paged = max(1, (int)($_GET['paged'] ?? 1));
        $per_page = 20;
        $offset = ($paged - 1) * $per_page;

        return [
            'paged' => $paged,
            'per_page' => $per_page,
            'offset' => $offset
        ];
    }

    /**
     * Gerar URL de exportação CSV com filtros e nonce
     *
     * @param array $filters Filtros aplicados
     * @return string URL de exportação
     */
    private function get_export_url($filters)
    {
        $export_url = add_query_arg([
            'page' => 'iw8-wa-clicks',
            'export' => 'csv',
            '_wpnonce' => wp_create_nonce('iw8_wa_export')
        ], admin_url('admin.php'));

        // Adicionar filtros se existirem
        if (!empty($filters['s'])) {
            $export_url = add_query_arg('s', urlencode($filters['s']), $export_url);
        }
        if (!empty($filters['from'])) {
            $export_url = add_query_arg('from', $filters['from'], $export_url);
        }
        if (!empty($filters['to'])) {
            $export_url = add_query_arg('to', $filters['to'], $export_url);
        }

        return $export_url;
    }
}
