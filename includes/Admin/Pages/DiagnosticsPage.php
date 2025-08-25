<?php

/**
 * Página de diagnóstico do plugin
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
 * Classe DiagnosticsPage
 */
class DiagnosticsPage
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
        // Verificar se as funções do WordPress estão disponíveis
        if (!function_exists('current_user_can') || !function_exists('wp_die') || !function_exists('get_option')) {
            return;
        }

        // Verificar permissões
        if (!current_user_can('manage_options')) {
            wp_die(__('Você não tem permissão para acessar esta página.', 'iw8-wa-click-tracker'));
        }

        // ✅ processe o POST (teste e logs) AQUI, antes de renderizar
        $this->process_test_insert();
        $this->process_log_actions();

        // Obter contagem total de cliques
        $total_clicks = $this->repository->countTotals();
        $total_count = $total_clicks['total'] ?? 0;

?>
        <div class="wrap">
            <h1><?php _e('Diagnóstico - WA Cliques', 'iw8-wa-click-tracker'); ?></h1>

            <?php $this->render_admin_notices(); ?>

            <div class="iw8-diagnostics-content">
                <div class="iw8-stats-section">
                    <h2><?php _e('Estatísticas', 'iw8-wa-click-tracker'); ?></h2>
                    <p><?php _e('Total de cliques registrados:', 'iw8-wa-click-tracker'); ?> <strong><?php echo function_exists('number_format') ? number_format($total_count) : $total_count; ?></strong></p>
                </div>

                <div class="iw8-test-section">
                    <h2><?php _e('Inserir Registro de Teste', 'iw8-wa-click-tracker'); ?></h2>
                    <p><?php _e('Clique no botão abaixo para inserir um registro de teste no banco de dados.', 'iw8-wa-click-tracker'); ?></p>

                    <form method="post" action="">
                        <?php
                        if (function_exists('wp_nonce_field')) {
                            wp_nonce_field('iw8_wa_diag');
                        }
                        ?>
                        <input type="submit" name="insert_test" value="<?php _e('Inserir Registro de Teste', 'iw8-wa-click-tracker'); ?>" class="button button-primary" />
                    </form>
                </div>

                <div class="iw8-recent-section">
                    <h2><?php _e('Últimos 20 Registros', 'iw8-wa-click-tracker'); ?></h2>
                    <?php $this->render_recent_clicks(); ?>
                </div>

                <?php $this->render_logs_section(); ?>
            </div>
        </div>
        <?php
    }

    /**
     * Renderizar notices administrativos
     *
     * @return void
     */
    private function render_admin_notices()
    {
        // Verificar se as funções do WordPress estão disponíveis
        if (!function_exists('add_settings_error') || !function_exists('settings_errors')) {
            return;
        }

        // Verificar se telefone está configurado
        if (!\IW8\WaClickTracker\Utils\Helpers::isPhoneConfigured()) {
        ?>
            <div class="notice notice-warning">
                <p>
                    <strong><?php _e('Atenção:', 'iw8-wa-click-tracker'); ?></strong>
                    <?php _e('O telefone não está configurado. O rastreamento de cliques não funcionará até que um telefone válido seja configurado.', 'iw8-wa-click-tracker'); ?>
                </p>
            </div>
        <?php
        }

        // Mostrar notices de diagnóstico
        settings_errors('iw8_wa_diag');
    }

    /**
     * Processar inserção de registro de teste
     *
     * @return void
     */
    private function process_test_insert()
    {
        if (!function_exists('add_settings_error') || !function_exists('home_url') || !function_exists('get_current_user_id') || !function_exists('current_time')) {
            return;
        }
        if (!isset($_POST['insert_test'])) {
            return;
        }

        if (function_exists('error_log')) {
            error_log('[IW8_DIAG] Recebi POST insert_test');
        }

        if (!check_admin_referer('iw8_wa_diag') || !current_user_can('manage_options')) {
            if (function_exists('error_log')) error_log('[IW8_DIAG] Falha de nonce/capability');
            wp_die(__('Ação não autorizada.', 'iw8-wa-click-tracker'));
        }

        $phone = \IW8\WaClickTracker\Utils\Helpers::getConfiguredPhone();
        if (empty($phone)) {
            add_settings_error(
                'iw8_wa_diag',
                'iw8_wa_diag_error',
                __('Não é possível inserir registro de teste: telefone não configurado.', 'iw8-wa-click-tracker'),
                'error'
            );
            if (function_exists('error_log')) error_log('[IW8_DIAG] Telefone vazio: abortando');
            // Recarrega a página para exibir a notice
            $url = function_exists('menu_page_url') ? menu_page_url('iw8-wa-clicks-dbg', false) : admin_url('admin.php?page=iw8-wa-clicks-dbg');
            if (function_exists('wp_safe_redirect')) {
                wp_safe_redirect($url);
                exit;
            }
            return;
        }

        $test_data = [
            'url'          => 'https://api.whatsapp.com/send?phone=' . $phone . '&text=teste',
            'page_url'     => home_url('/'),
            'element_tag'  => 'ADMIN',
            'element_text' => 'Inserção de teste',
            'user_id'      => get_current_user_id() ?: null,
            'user_agent'   => 'WP-Admin/Diag',
            'clicked_at'   => current_time('mysql'),
        ];

        if (function_exists('error_log')) {
            error_log('[IW8_DIAG] Inserindo teste: ' . wp_json_encode($test_data));
        }

        $result = $this->repository->insertClick($test_data);

        if (is_wp_error($result)) {
            if (function_exists('error_log')) {
                global $wpdb;
                error_log('[IW8_DIAG] ERRO insert: ' . $result->get_error_message() . ' | wpdb: ' . (isset($wpdb) ? $wpdb->last_error : 'n/a'));
            }
            add_settings_error(
                'iw8_wa_diag',
                'iw8_wa_diag_error',
                __('Erro ao inserir registro de teste: ' . $result->get_error_message(), 'iw8-wa-click-tracker'),
                'error'
            );
        } else {
            if (function_exists('error_log')) {
                error_log('[IW8_DIAG] OK insert. ID=' . $result);
            }
            add_settings_error(
                'iw8_wa_diag',
                'iw8_wa_diag_success',
                __('Registro de teste inserido com sucesso! ID: ' . $result, 'iw8-wa-click-tracker'),
                'success'
            );
        }

        // Redirect pós-POST para exibir notice e atualizar a lista
        $url = function_exists('menu_page_url') ? menu_page_url('iw8-wa-clicks-dbg', false) : admin_url('admin.php?page=iw8-wa-clicks-dbg');
        if (function_exists('wp_safe_redirect')) {
            wp_safe_redirect($url);
            exit;
        }
    }

    /**
     * Processar ações de logs (download, limpar)
     *
     * @return void
     */
    private function process_log_actions()
    {
        // Verificar se as funções do WordPress estão disponíveis
        if (!function_exists('check_admin_referer') || !function_exists('current_user_can') || !function_exists('wp_die')) {
            return;
        }

        // Verificar se há ação solicitada
        if (!isset($_GET['action'])) {
            return;
        }

        $action = sanitize_text_field($_GET['action']);

        // Verificar se é uma ação válida
        if (!in_array($action, ['download_logs', 'clear_logs'])) {
            return;
        }

        // Verificar nonce e permissões
        if (!check_admin_referer('iw8_wa_diag') || !current_user_can('manage_options')) {
            wp_die(__('Você não tem permissão para executar esta ação.', 'iw8-wa-click-tracker'));
        }

        $log_file = \IW8\WaClickTracker\Core\Logger::getPluginLogFile();

        if (!$log_file) {
            add_settings_error(
                'iw8_wa_diag',
                'iw8_wa_diag_error',
                __('Nenhum arquivo de log encontrado para esta ação.', 'iw8-wa-click-tracker'),
                'error'
            );
            return;
        }

        switch ($action) {
            case 'download_logs':
                if (function_exists('download_url')) {
                    $upload_dir = wp_upload_dir();
                    $filename = basename($log_file);
                    $file_path = $upload_dir['path'] . '/' . $filename;

                    if (file_exists($file_path)) {
                        $download_file = download_url($file_path);
                        if (is_wp_error($download_file)) {
                            add_settings_error(
                                'iw8_wa_diag',
                                'iw8_wa_diag_error',
                                __('Erro ao fazer download do arquivo de log: ' . $download_file->get_error_message(), 'iw8-wa-click-tracker'),
                                'error'
                            );
                        } else {
                            wp_redirect($download_file);
                            exit;
                        }
                    } else {
                        add_settings_error(
                            'iw8_wa_diag',
                            'iw8_wa_diag_error',
                            __('Arquivo de log não encontrado para download.', 'iw8-wa-click-tracker'),
                            'error'
                        );
                    }
                } else {
                    add_settings_error(
                        'iw8_wa_diag',
                        'iw8_wa_diag_error',
                        __('Função download_url não disponível.', 'iw8-wa-click-tracker'),
                        'error'
                    );
                }
                break;
            case 'clear_logs':
                if (function_exists('unlink')) {
                    // Tentar deletar o arquivo diretamente
                    if (file_exists($log_file)) {
                        if (unlink($log_file)) {
                            add_settings_error(
                                'iw8_wa_diag',
                                'iw8_wa_diag_success',
                                __('Arquivo de log limpo com sucesso!', 'iw8-wa-click-tracker'),
                                'success'
                            );
                        } else {
                            add_settings_error(
                                'iw8_wa_diag',
                                'iw8_wa_diag_error',
                                __('Erro ao limpar o arquivo de log. Verifique permissões.', 'iw8-wa-click-tracker'),
                                'error'
                            );
                        }
                    } else {
                        add_settings_error(
                            'iw8_wa_diag',
                            'iw8_wa_diag_error',
                            __('Arquivo de log não encontrado para limpeza.', 'iw8-wa-click-tracker'),
                            'error'
                        );
                    }
                } else {
                    add_settings_error(
                        'iw8_wa_diag',
                        'iw8_wa_diag_error',
                        __('Função unlink não disponível.', 'iw8-wa-click-tracker'),
                        'error'
                    );
                }
                break;
        }
    }

    /**
     * Formatar linhas de log para melhor visualização
     *
     * @param array $lines Linhas de log brutas.
     * @return string Formatação HTML das linhas de log.
     */
    private function format_log_lines($lines)
    {
        $formatted_lines = [];
        foreach ($lines as $line) {
            // Tenta identificar o tipo de log (INFO, WARNING, ERROR)
            if (strpos($line, '[INFO]') !== false) {
                $formatted_lines[] = '<span style="color: #555;">' . esc_html($line) . '</span>';
            } elseif (strpos($line, '[WARNING]') !== false) {
                $formatted_lines[] = '<span style="color: #ffa500;">' . esc_html($line) . '</span>';
            } elseif (strpos($line, '[ERROR]') !== false) {
                $formatted_lines[] = '<span style="color: #ff0000;">' . esc_html($line) . '</span>';
            } else {
                $formatted_lines[] = esc_html($line);
            }
        }
        return implode("\n", $formatted_lines);
    }

    /**
     * Renderizar seção de logs
     *
     * @return void
     */
    private function render_logs_section()
    {
        // Verificar se as funções do WordPress estão disponíveis
        if (!function_exists('admin_url') || !function_exists('wp_create_nonce') || !function_exists('sprintf')) {
            return;
        }

        ?>
        <div class="iw8-logs-section">
            <h2><?php _e('Logs do Sistema', 'iw8-wa-click-tracker'); ?></h2>
            <p><?php _e('Visualize os logs do plugin para debug e diagnóstico.', 'iw8-wa-click-tracker'); ?></p>

            <?php
            // Verificar se arquivo de log existe
            $log_file = \IW8\WaClickTracker\Core\Logger::getPluginLogFile();

            if ($log_file) {
                if (function_exists('file_get_contents')) {
                    $log_content = file_get_contents($log_file);
                    if (function_exists('explode')) {
                        $log_lines = explode("\n", $log_content);

                        if (function_exists('array_slice')) {
                            // Mostrar apenas as últimas 100 linhas
                            $recent_lines = array_slice($log_lines, -100);
                            if (function_exists('implode')) {
                                // Formatar logs para melhor visualização
                                $formatted_logs = $this->format_log_lines($recent_lines);

                                echo '<div class="iw8-logs-content">';
                                echo '<pre style="background: #f6f7f7; border: 1px solid #dcdcde; padding: 15px; max-height: 400px; overflow-y: auto; font-family: monospace; font-size: 12px; white-space: pre-wrap; line-height: 1.4;">';
                                echo $formatted_logs;
                                echo '</pre>';
                                echo '</div>';

                                echo '<div class="iw8-logs-actions">';
                                echo '<a href="' . admin_url('admin.php?page=iw8-wa-clicks-dbg&action=download_logs&_wpnonce=' . wp_create_nonce('iw8_wa_diag')) . '" class="button button-secondary">';
                                echo __('Download dos Logs', 'iw8-wa-click-tracker');
                                echo '</a>';

                                echo '<a href="' . admin_url('admin.php?page=iw8-wa-clicks-dbg&action=clear_logs&_wpnonce=' . wp_create_nonce('iw8_wa_diag')) . '" class="button button-secondary" onclick="return confirm(\'' . __('Tem certeza que deseja limpar os logs?', 'iw8-wa-click-tracker') . '\')">';
                                echo __('Limpar Logs', 'iw8-wa-click-tracker');
                                echo '</a>';

                                echo '<span class="description">';
                                if (function_exists('count')) {
                                    echo sprintf(
                                        __('Arquivo: %s (%s linhas)', 'iw8-wa-click-tracker'),
                                        function_exists('basename') ? basename($log_file) : $log_file,
                                        count($log_lines)
                                    );
                                } else {
                                    echo sprintf(
                                        __('Arquivo: %s', 'iw8-wa-click-tracker'),
                                        function_exists('basename') ? basename($log_file) : $log_file
                                    );
                                }
                                echo '</span>';
                                echo '</div>';
                            } else {
                                echo '<div class="notice notice-error">';
                                echo '<p>' . __('Função implode não disponível.', 'iw8-wa-click-tracker') . '</p>';
                                echo '</div>';
                            }
                        } else {
                            echo '<div class="notice notice-error">';
                            echo '<p>' . __('Função array_slice não disponível.', 'iw8-wa-click-tracker') . '</p>';
                            echo '</div>';
                        }
                    } else {
                        echo '<div class="notice notice-error">';
                        echo '<p>' . __('Função explode não disponível.', 'iw8-wa-click-tracker') . '</p>';
                        echo '</div>';
                    }
                } else {
                    echo '<div class="notice notice-error">';
                    echo '<p>' . __('Função file_get_contents não disponível.', 'iw8-wa-click-tracker') . '</p>';
                    echo '</div>';
                }
            } else {
                echo '<div class="notice notice-info">';
                echo '<p>' . __('Nenhum arquivo de log encontrado. Os logs podem estar sendo gravados no sistema ou no WordPress debug log.', 'iw8-wa-click-tracker') . '</p>';
                echo '</div>';

                // Mostrar informações sobre onde encontrar logs
                echo '<div class="iw8-logs-info">';
                echo '<h4>' . __('Onde encontrar logs:', 'iw8-wa-click-tracker') . '</h4>';
                echo '<ul>';
                echo '<li><strong>WordPress Debug Log:</strong> ' . (defined('WP_DEBUG_LOG') && WP_DEBUG_LOG ? 'HABILITADO' : 'DESABILITADO') . '</li>';
                echo '<li><strong>Localização WP:</strong> wp-content/debug.log</li>';
                echo '<li><strong>Logs do Sistema:</strong> Verificar logs do servidor web (Apache/Nginx)</li>';
                echo '<li><strong>XAMPP:</strong> xampp/apache/logs/error.log</li>';
                echo '</ul>';
                echo '</div>';
            }
            ?>
        </div>
    <?php
    }

    /**
     * Renderizar lista de cliques recentes (somente TESTES do admin)
     *
     * @return void
     */
    private function render_recent_clicks()
    {
        // Verificar se as funções do WordPress estão disponíveis
        if (!function_exists('esc_html') || !function_exists('esc_attr')) {
            return;
        }

        // Buscar mais itens e filtrar em memória para garantir que só testes apareçam
        $all = $this->repository->list([], ['per_page' => 200, 'offset' => 0]);

        // Mantém apenas registros criados pelo diagnóstico (ADMIN / WP-Admin/Diag)
        $tests = array_values(array_filter($all, function ($c) {
            $tag = isset($c->element_tag) ? strtoupper((string)$c->element_tag) : '';
            $ua  = isset($c->user_agent) ? (string)$c->user_agent : '';
            if ($tag === 'ADMIN') {
                return true;
            }
            return stripos($ua, 'WP-Admin/Diag') !== false;
        }));

        // Limitar a 20
        $recent_clicks = array_slice($tests, 0, 20);

        // Título da seção (apenas testes)
        echo '<h2>' . esc_html__('Últimos 20 Testes (Admin)', 'iw8-wa-click-tracker') . '</h2>';

        if (empty($recent_clicks)) {
            echo '<p>' . esc_html__('Nenhum registro de teste ainda.', 'iw8-wa-click-tracker') . '</p>';
            return;
        }

    ?>
        <div class="iw8-recent-table">
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th><?php _e('ID', 'iw8-wa-click-tracker'); ?></th>
                        <th><?php _e('Data/Hora', 'iw8-wa-click-tracker'); ?></th>
                        <th><?php _e('URL', 'iw8-wa-click-tracker'); ?></th>
                        <th><?php _e('Página', 'iw8-wa-click-tracker'); ?></th>
                        <th><?php _e('Elemento', 'iw8-wa-click-tracker'); ?></th>
                        <th><?php _e('Texto', 'iw8-wa-click-tracker'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recent_clicks as $click): ?>
                        <tr>
                            <td><?php echo esc_html($click->id); ?></td>
                            <td><?php echo esc_html($click->clicked_at); ?></td>
                            <td>
                                <a href="<?php echo esc_url($click->url); ?>" target="_blank" rel="noopener">
                                    <?php
                                    $u = (string) $click->url;
                                    echo esc_html((function_exists('substr') ? substr($u, 0, 50) : $u))
                                        . ((function_exists('strlen') && strlen($u) > 50) ? '...' : '');
                                    ?>
                                </a>
                            </td>
                            <td>
                                <?php if (!empty($click->page_url)): ?>
                                    <a href="<?php echo esc_url($click->page_url); ?>" target="_blank" rel="noopener">
                                        <?php
                                        $p = (string) $click->page_url;
                                        echo esc_html((function_exists('substr') ? substr($p, 0, 30) : $p))
                                            . ((function_exists('strlen') && strlen($p) > 30) ? '...' : '');
                                        ?>
                                    </a>
                                <?php else: ?>
                                    -
                                <?php endif; ?>
                            </td>
                            <td><?php echo esc_html($click->element_tag ?: '-'); ?></td>
                            <td>
                                <?php
                                $t = $click->element_text ?: '';
                                echo esc_html((function_exists('substr') ? substr($t, 0, 30) : $t))
                                    . ((function_exists('strlen') && strlen($t) > 30) ? '...' : '');
                                ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
<?php
    }
}
