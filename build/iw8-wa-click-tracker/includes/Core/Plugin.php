<?php
/**
 * Classe principal do plugin IW8 – Rastreador de Cliques WhatsApp
 *
 * @package IW8_WaClickTracker\Core
 * @version 1.3.0
 */

namespace IW8\WaClickTracker\Core;

// Prevenir acesso direto
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Classe principal do plugin
 */
class Plugin
{
    /**
     * Versão do plugin
     *
     * @var string
     */
    private $version;

    /**
     * Instância da classe TableClicks
     *
     * @var \IW8\WaClickTracker\Database\TableClicks
     */
    private $table_clicks;

    /**
     * Instância da classe Versions
     *
     * @var \IW8\WaClickTracker\Core\Versions
     */
    private $versions;

    /**
     * Instância da classe Tracker
     *
     * @var \IW8\WaClickTracker\Frontend\Tracker
     */
    private $tracker;

    /**
     * Instância da classe Assets
     *
     * @var \IW8\WaClickTracker\Core\Assets
     */
    private $assets;

    /**
     * Instância da classe ClickController
     *
     * @var \IW8\WaClickTracker\Ajax\ClickController
     */
    private $click_controller;

    /**
     * Instância da classe Menu
     *
     * @var \IW8\WaClickTracker\Admin\Menu
     */
    private $admin_menu;

    /**
     * Instância da classe Updater
     *
     * @var \IW8\WaClickTracker\Core\Updater
     */
    private $updater;

    /**
     * Construtor da classe
     */
    public function __construct()
    {
        $this->version = IW8_WA_CLICK_TRACKER_VERSION;
        
        // Inicializar componentes de banco de dados
        $this->table_clicks = new \IW8\WaClickTracker\Database\TableClicks();
        $this->versions = new \IW8\WaClickTracker\Core\Versions();
        
        // Inicializar componentes de frontend e AJAX
        $this->tracker = new \IW8\WaClickTracker\Frontend\Tracker();
        $this->assets = new \IW8\WaClickTracker\Core\Assets();
        $this->click_controller = new \IW8\WaClickTracker\Ajax\ClickController();
        
        // Inicializar componente de menu admin
        $this->admin_menu = new \IW8\WaClickTracker\Admin\Menu();
        
        // Inicializar componente de updater
        $this->updater = new \IW8\WaClickTracker\Core\Updater();
    }

    /**
     * Inicializar o plugin
     *
     * @return void
     */
    public function init()
    {
        // Configurar hooks básicos
        $this->setup_hooks();
    }

    /**
     * Configurar hooks básicos
     *
     * @return void
     */
    private function setup_hooks()
    {
        // Verificar se as funções do WordPress estão disponíveis
        if (!function_exists('add_action')) {
            return;
        }

        // Hook para verificar e garantir tabela (executa em admin_init para ser seguro)
        add_action('admin_init', [$this, 'ensure_database']);
        
        // Hook para verificar upgrades de versão (executa em init para ser idempotente)
        add_action('init', [$this, 'check_upgrades']);
        
        // Hook para enfileirar tracker JavaScript no frontend
        add_action('wp_enqueue_scripts', [$this->tracker, 'maybe_enqueue_tracker_js']);
        
        // Hooks AJAX para cliques
        add_action('wp_ajax_iw8_wa_click', [$this->click_controller, 'handle_click']);
        add_action('wp_ajax_nopriv_iw8_wa_click', [$this->click_controller, 'handle_click']);
        
        // DEBUG: Log para verificar se hooks AJAX foram registrados
        if (function_exists('error_log')) {
            error_log('IW8_WA_CLICK_TRACKER DEBUG: Hooks AJAX registrados');
            error_log('IW8_WA_CLICK_TRACKER DEBUG: wp_ajax_iw8_wa_click registrado');
            error_log('IW8_WA_CLICK_TRACKER DEBUG: wp_ajax_nopriv_iw8_wa_click registrado');
        }
        
        // Hook para registrar menu administrativo
        add_action('admin_menu', [$this->admin_menu, 'register']);
        
        // Hook para notices administrativos
        add_action('admin_notices', [$this, 'render_admin_notices']);
        
        // Hook para processar export CSV
        add_action('admin_init', [$this, 'process_csv_export']);
        
        // Hook para inicializar updater
        add_action('init', [$this->updater, 'init']);
    }

    /**
     * Garantir que o banco de dados está configurado
     *
     * @return void
     */
    public function ensure_database()
    {
        try {
            // Garantir que a tabela existe
            $this->table_clicks->ensure_table();
            \IW8\WaClickTracker\Core\Logger::database('Tabela verificada/garantida com sucesso');
        } catch (\Exception $e) {
            \IW8\WaClickTracker\Core\Logger::databaseError($e->getMessage(), [
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);
        }
    }

    /**
     * Verificar e executar upgrades necessários
     *
     * @return void
     */
    public function check_upgrades()
    {
        try {
            // Verificar se há upgrades necessários
            if ($this->versions->needs_upgrade()) {
                $this->versions->maybe_upgrade();
                \IW8\WaClickTracker\Core\Logger::upgrade('Upgrade de versão executado com sucesso');
            }
        } catch (\Exception $e) {
            \IW8\WaClickTracker\Core\Logger::upgradeError($e->getMessage(), [
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);
        }
    }

    /**
     * Renderizar notices administrativos
     *
     * @return void
     */
    public function render_admin_notices()
    {
        // Só mostrar notices nas páginas do plugin
        $screen = get_current_screen();
        if (!$screen || strpos($screen->id, 'iw8-wa-clicks') === false) {
            return;
        }

        // Verificar se telefone está configurado
        if (!\IW8\WaClickTracker\Utils\Helpers::isPhoneConfigured()) {
            ?>
            <div class="notice notice-warning">
                <p>
                    <strong><?php _e('IW8 – Rastreador de Cliques WhatsApp:', 'iw8-wa-click-tracker'); ?></strong>
                    <?php _e('O telefone não está configurado. O rastreamento de cliques não funcionará até que um telefone válido seja configurado em', 'iw8-wa-click-tracker'); ?>
                    <a href="<?php echo admin_url('admin.php?page=iw8-wa-clicks-settings'); ?>">
                        <?php _e('Configurações', 'iw8-wa-click-tracker'); ?>
                    </a>.
                </p>
            </div>
            <?php
        }
    }

    /**
     * Obter versão do plugin
     *
     * @return string
     */
    public function get_version()
    {
        return $this->version;
    }

    /**
     * Obter instância da tabela de cliques
     *
     * @return \IW8\WaClickTracker\Database\TableClicks
     */
    public function get_table_clicks()
    {
        return $this->table_clicks;
    }

    /**
     * Obter instância de versões
     *
     * @return \IW8\WaClickTracker\Core\Versions
     */
    public function get_versions()
    {
        return $this->versions;
    }

    /**
     * Obter instância do tracker
     *
     * @return \IW8\WaClickTracker\Frontend\Tracker
     */
    public function get_tracker()
    {
        return $this->tracker;
    }

    /**
     * Obter instância de assets
     *
     * @return \IW8\WaClickTracker\Core\Assets
     */
    public function get_assets()
    {
        return $this->assets;
    }

    /**
     * Obter instância do controller de cliques
     *
     * @return \IW8\WaClickTracker\Ajax\ClickController
     */
    public function get_click_controller()
    {
        return $this->click_controller;
    }

    /**
     * Obter instância do menu admin
     *
     * @return \IW8\WaClickTracker\Admin\Menu
     */
    public function get_admin_menu()
    {
        return $this->admin_menu;
    }

    /**
     * Obter instância do updater
     *
     * @return \IW8\WaClickTracker\Core\Updater
     */
    public function get_updater()
    {
        return $this->updater;
    }

    /**
     * Processar export CSV
     *
     * @return void
     */
    public function process_csv_export()
    {
        // Verificar se é uma requisição de export CSV
        if (!isset($_GET['page']) || $_GET['page'] !== 'iw8-wa-clicks' || 
            !isset($_GET['export']) || $_GET['export'] !== 'csv') {
            return;
        }

        // Verificar nonce
        if (!check_admin_referer('iw8_wa_export')) {
            wp_die(__('Ação não autorizada.', 'iw8-wa-click-tracker'));
        }

        // Verificar permissões
        if (!current_user_can('manage_options')) {
            wp_die(__('Você não tem permissão para exportar dados.', 'iw8-wa-click-tracker'));
        }

        // Verificar se telefone está configurado
        if (!\IW8\WaClickTracker\Utils\Helpers::isPhoneConfigured()) {
            wp_die(__('Não é possível exportar: telefone não configurado.', 'iw8-wa-click-tracker'));
        }

        try {
            // Preparar filtros
            $raw_filters = [
                's' => $_GET['s'] ?? '',
                'from' => $_GET['from'] ?? '',
                'to' => $_GET['to'] ?? ''
            ];

            // Instanciar exportador e preparar filtros
            $exporter = new \IW8\WaClickTracker\Export\CsvExporter();
            $filters = $exporter->prepareFilters($raw_filters);

            // Executar export
            $exporter->outputCsv($filters);

        } catch (\Exception $e) {
            // Log do erro
            if (function_exists('error_log')) {
                error_log('IW8 WaClickTracker CSV Export Error: ' . $e->getMessage());
            }

            // Retornar erro amigável
            wp_die(__('Erro ao exportar dados. Tente novamente.', 'iw8-wa-click-tracker'));
        }
    }
}
