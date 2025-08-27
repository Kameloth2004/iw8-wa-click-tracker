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

            <!-- Formulário de Exportação CSV (POST para admin-post.php) -->
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="margin:16px 0;">
                <input type="hidden" name="action" value="iw8_wa_export_csv" />
                <?php wp_nonce_field('iw8_wa_export'); ?>

                <fieldset style="display:flex; gap:12px; flex-wrap:wrap; align-items:flex-end;">
                    <label>
                        <span><?php esc_html_e('De', 'iw8-wa-click-tracker'); ?></span><br>
                        <input type="date" name="date_from">
                    </label>
                    <label>
                        <span><?php esc_html_e('Até', 'iw8-wa-click-tracker'); ?></span><br>
                        <input type="date" name="date_to">
                    </label>
                    <label>
                        <span><?php esc_html_e('Página (prefixo)', 'iw8-wa-click-tracker'); ?></span><br>
                        <input type="url" name="page_url" placeholder="https://seusite.com/">
                    </label>
                    <label>
                        <span><?php esc_html_e('Tag do elemento', 'iw8-wa-click-tracker'); ?></span><br>
                        <input type="text" name="element_tag" placeholder="a, button, etc.">
                    </label>
                    <label>
                        <span><?php esc_html_e('User ID', 'iw8-wa-click-tracker'); ?></span><br>
                        <input type="number" name="user_id" min="1">
                    </label>
                    <label>
                        <span><?php esc_html_e('Limite', 'iw8-wa-click-tracker'); ?></span><br>
                        <input type="number" name="limit" min="1" max="5000" value="1000">
                    </label>
                    <label>
                        <span><?php esc_html_e('Ordem', 'iw8-wa-click-tracker'); ?></span><br>
                        <select name="order">
                            <option value="desc" selected><?php esc_html_e('Mais recentes primeiro', 'iw8-wa-click-tracker'); ?></option>
                            <option value="asc"><?php esc_html_e('Mais antigas primeiro', 'iw8-wa-click-tracker'); ?></option>
                        </select>
                    </label>

                    <button type="submit" class="button button-primary">
                        <?php esc_html_e('Exportar CSV', 'iw8-wa-click-tracker'); ?>
                    </button>
                </fieldset>
            </form>

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
     * Renderizar filtros (para a tabela, via GET)
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

                    <a href="<?php echo esc_url(admin_url('admin.php?page=iw8-wa-clicks')); ?>"
                        class="button">
                        <?php _e('Limpar', 'iw8-wa-click-tracker'); ?>
                    </a>
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
                                    // -->
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
            'base'      => add_query_arg('paged', '%#%'),
            'format'    => '',
            'prev_text' => __('&laquo;'),
            'next_text' => __('&raquo;'),
            'total'     => $total_pages,
            'current'   => $current_page,
            'type'      => 'plain'
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
            'paged'    => $paged,
            'per_page' => $per_page,
            'offset'   => $offset
        ];
    }
}
