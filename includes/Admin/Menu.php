<?php
/**
 * Classe para gerenciar o menu administrativo do plugin
 *
 * @package IW8_WaClickTracker\Admin
 * @version 1.3.0
 */

namespace IW8\WaClickTracker\Admin;

// Prevenir acesso direto
if (!defined('ABSPATH')) {
    exit;
}

require_once __DIR__ . "/Pages/HubPage.php";

/**
 * Classe Menu
 */
class Menu
{
    /**
     * Instância da página de cliques
     *
     * @var \IW8\WaClickTracker\Admin\Pages\ClicksPage
     */
    private $clicks_page;

    /**
     * Instância da página de diagnóstico
     *
     * @var \IW8\WaClickTracker\Admin\Pages\DiagnosticsPage
     */
    private $diagnostics_page;

    /**
     * Instância da página de configurações
     *
     * @var \IW8\WaClickTracker\Admin\Pages\SettingsPage
     */
    private $settings_page;
    
  /**
 * Instância da página do Hub (Envio automático)
 *
 * @var \IW8\WaClickTracker\Admin\Pages\HubPage
 */
private $hub_page;

    /**
     * Construtor da classe
     */
    public function __construct()
    {
        // Verificar se as funções do WordPress estão disponíveis
        if (!function_exists('add_menu_page') || !function_exists('add_submenu_page')) {
            return;
        }

        require_once __DIR__ . "/Pages/ClicksPage.php";
        require_once __DIR__ . "/Pages/DiagnosticsPage.php";
        require_once __DIR__ . "/Pages/SettingsPage.php";
        $this->clicks_page = new \IW8\WaClickTracker\Admin\Pages\ClicksPage();
        $this->diagnostics_page = new \IW8\WaClickTracker\Admin\Pages\DiagnosticsPage();
        $this->settings_page = new \IW8\WaClickTracker\Admin\Pages\SettingsPage();
        $this->hub_page = new \IW8\WaClickTracker\Admin\Pages\HubPage();
    }

    /**
     * Registrar menu e submenus
     *
     * @return void
     */
    public function register()
    {
        // Verificar se as funções do WordPress estão disponíveis
        if (!function_exists('add_menu_page') || !function_exists('add_submenu_page') || !function_exists('__')) {
            return;
        }

        // Menu principal
        add_menu_page(
            __('WA Cliques', 'iw8-wa-click-tracker'),
            __('WA Cliques', 'iw8-wa-click-tracker'),
            'manage_options',
            'iw8-wa-clicks',
            [$this->clicks_page, 'render'],
            'dashicons-chart-line',
            56
        );

        // Submenu - Relatórios (mesma página do menu principal)
        add_submenu_page(
            'iw8-wa-clicks',
            __('Relatórios', 'iw8-wa-click-tracker'),
            __('Relatórios', 'iw8-wa-click-tracker'),
            'manage_options',
            'iw8-wa-clicks',
            [$this->clicks_page, 'render']
        );

        // Submenu - Diagnóstico
        add_submenu_page(
            'iw8-wa-clicks',
            __('Diagnóstico', 'iw8-wa-click-tracker'),
            __('Diagnóstico', 'iw8-wa-click-tracker'),
            'manage_options',
            'iw8-wa-clicks-dbg',
            [$this->diagnostics_page, 'render']
        );

        // Submenu - Configurações
        add_submenu_page(
            'iw8-wa-clicks',
            __('Configurações', 'iw8-wa-click-tracker'),
            __('Configurações', 'iw8-wa-click-tracker'),
            'manage_options',
            'iw8-wa-clicks-settings',
            [$this->settings_page, 'render']
        );
        
        // Submenu - Hub (Envio automático)
add_submenu_page(
    'iw8-wa-clicks',
    __('Hub (Envio automático)', 'iw8-wa-click-tracker'),
    __('Hub (Envio automático)', 'iw8-wa-click-tracker'),
    'manage_options',
    'iw8-wa-clicks-hub',
    [$this->hub_page, 'render']
);
    }
}
