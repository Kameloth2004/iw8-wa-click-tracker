<?php

/**
 * Plugin Name: IW8 – Rastreador de Cliques WhatsApp
 * Plugin URI: https://github.com/iw8/iw8-wa-click-tracker
 * Description: Plugin para rastrear cliques em links do WhatsApp e gerar relatórios detalhados
 * Version: 1.4.1
 * Requires at least: 6.0
 * Tested up to: 6.4
 * Requires PHP: 7.4
 * Author: Adriano Marques
 * Author URI: https://iw8.dev
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: iw8-wa-click-tracker
 * Domain Path: /languages
 * Update URI: https://github.com/kameloth2004/iw8-wa-click-tracker
 * Network: false
 *
 * @package IW8_WaClickTracker
 */

// Bloqueia acesso direto.
if (!defined('ABSPATH')) {
    exit;
}

/**
 * === Versão do plugin (lida do cabeçalho) ===
 * Define IW8_WA_CT_VERSION para reuso.
 */
if (!defined('IW8_WA_CT_VERSION')) {
    $data = function_exists('get_file_data')
        ? get_file_data(__FILE__, ['Version' => 'Version'])
        : ['Version' => '1.4.1'];
    define('IW8_WA_CT_VERSION', !empty($data['Version']) ? $data['Version'] : '1.4.1');
}

/** === Constantes base do plugin === */
if (!defined('IW8_WA_CLICK_TRACKER_PLUGIN_FILE')) {
    define('IW8_WA_CLICK_TRACKER_PLUGIN_FILE', __FILE__);
}
if (!defined('IW8_WA_CLICK_TRACKER_PLUGIN_DIR')) {
    define('IW8_WA_CLICK_TRACKER_PLUGIN_DIR', plugin_dir_path(__FILE__));
}
if (!defined('IW8_WA_CLICK_TRACKER_PLUGIN_URL')) {
    define('IW8_WA_CLICK_TRACKER_PLUGIN_URL', plugin_dir_url(__FILE__));
}
if (!defined('IW8_WA_CLICK_TRACKER_PLUGIN_BASENAME')) {
    define('IW8_WA_CLICK_TRACKER_PLUGIN_BASENAME', plugin_basename(__FILE__));
}
if (!defined('IW8_WA_CLICK_TRACKER_TEXT_DOMAIN')) {
    define('IW8_WA_CLICK_TRACKER_TEXT_DOMAIN', 'iw8-wa-click-tracker');
}
if (!defined('IW8_WA_DB_VERSION')) {
    define('IW8_WA_DB_VERSION', '1.1');
}
if (!defined('IW8_WA_CLICK_TRACKER_VERSION')) {
    // Mantém compat com código antigo que usa este nome.
    define('IW8_WA_CLICK_TRACKER_VERSION', IW8_WA_CT_VERSION);
}

/** === Autoload do Composer (precisa vir antes do uso de classes em src/) === */
$__vendorAutoload = IW8_WA_CLICK_TRACKER_PLUGIN_DIR . 'vendor/autoload.php';
if (is_readable($__vendorAutoload)) {
    require_once $__vendorAutoload;
} else {
    if (function_exists('error_log')) {
        error_log('IW8_WA: vendor/autoload.php não encontrado');
    }
}

/** === Autoload/arquivos “includes/” legados (usados por migrações/DB/Admin) === */
$__includesAutoload = IW8_WA_CLICK_TRACKER_PLUGIN_DIR . 'includes/autoload.php';
if (is_readable($__includesAutoload)) {
    require_once $__includesAutoload;
}

/** Núcleo de segurança (arquivo sem PSR-4) */
$__securityFile = IW8_WA_CLICK_TRACKER_PLUGIN_DIR . 'includes/Core/Security.php';
if (is_readable($__securityFile)) {
    require_once $__securityFile;
    if (class_exists('\IW8\WaClickTracker\Core\Security')) {
        \IW8\WaClickTracker\Core\Security::init();
    }
}

/** Migrações (dbDelta) */
$__migrationsFile = IW8_WA_CLICK_TRACKER_PLUGIN_DIR . 'includes/install/db-migrations.php';
if (is_readable($__migrationsFile)) {
    require_once $__migrationsFile; // define iw8_wa_run_migrations()
}

/** Admin UI (arquivos não-PSR-4) */
$__adminFiles = [
    'includes/Admin/Actions/SavePhoneHandler.php',
    'includes/Admin/Actions/TokenHandlers.php',
    'includes/Admin/Pages/SettingsPage.php',
];
foreach ($__adminFiles as $__file) {
    $__full = IW8_WA_CLICK_TRACKER_PLUGIN_DIR . $__file;
    if (is_readable($__full)) {
        require_once $__full;
    }
}

/** === Updater centralizado (evita slug duplicado) === */
$__updaterFile = IW8_WA_CLICK_TRACKER_PLUGIN_DIR . 'includes/Core/Updater.php';
if (is_readable($__updaterFile)) {
    require_once $__updaterFile;
    if (class_exists('\IW8\WaClickTracker\Core\Updater')) {
        // Apenas no admin inicializamos PUC; Updater::init é idempotente.
        add_action('admin_init', ['IW8\WaClickTracker\Core\Updater', 'init']);
    }
}

/** === Rotas REST (registradas via autoload PSR-4 em src/Rest/*) === */
add_action('rest_api_init', function () {
    // Só tenta registrar se a classe PSR-4 estiver disponível:
    if (!class_exists(\IW8\WA\Rest\ApiRegistrar::class)) {
        if (function_exists('error_log')) {
            error_log('IW8_WA REST: ApiRegistrar não encontrado no autoload');
        }
        return;
    }
    $registrar = new \IW8\WA\Rest\ApiRegistrar();
    $registrar->register();

    if (function_exists('error_log')) {
        error_log('IW8_WA REST: rotas registradas (iw8-wa/v1)');
    }
});

/** === Checagem de versão do schema (roda em admin) === */
add_action('admin_init', function () {
    if (!function_exists('get_option')) {
        return;
    }
    $cur = get_option('iw8_wa_db_version');
    if ($cur !== IW8_WA_DB_VERSION && function_exists('iw8_wa_run_migrations')) {
        iw8_wa_run_migrations();
    }
});

/** === Export CSV (handler admin) === */
add_action('init', static function () {
    if (is_admin() && class_exists('\IW8\WaClickTracker\Admin\Actions\ExportCsvHandler')) {
        \IW8\WaClickTracker\Admin\Actions\ExportCsvHandler::register();
    }
}, 5);

/** === Requisitos mínimos (PHP/WP) === */
function iw8_wa_click_tracker_check_requirements(): bool
{
    if (version_compare(PHP_VERSION, '7.4', '<')) {
        add_action('admin_notices', 'iw8_wa_click_tracker_php_version_notice');
        return false;
    }
    if (version_compare(get_bloginfo('version'), '6.0', '<')) {
        add_action('admin_notices', 'iw8_wa_click_tracker_wp_version_notice');
        return false;
    }
    return true;
}
function iw8_wa_click_tracker_php_version_notice()
{
    echo '<div class="notice notice-error"><p>';
    echo sprintf(
        esc_html__('IW8 – Rastreador de Cliques WhatsApp requer PHP 7.4 ou superior. Sua versão atual é %s.', 'iw8-wa-click-tracker'),
        esc_html(PHP_VERSION)
    );
    echo '</p></div>';
}
function iw8_wa_click_tracker_wp_version_notice()
{
    echo '<div class="notice notice-error"><p>';
    echo esc_html__('IW8 – Rastreador de Cliques WhatsApp requer WordPress 6.0 ou superior.', 'iw8-wa-click-tracker');
    echo '</p></div>';
}

/** === Inicialização principal quando WP estiver pronto === */
function iw8_wa_click_tracker_init()
{
    if (!iw8_wa_click_tracker_check_requirements()) {
        return;
    }

    // Text domain.
    load_plugin_textdomain(
        IW8_WA_CLICK_TRACKER_TEXT_DOMAIN,
        false,
        dirname(IW8_WA_CLICK_TRACKER_PLUGIN_BASENAME) . '/languages'
    );

    // Inicializa o núcleo do plugin (se existir).
    try {
        if (class_exists('\IW8\WaClickTracker\Core\Plugin')) {
            $plugin = new \IW8\WaClickTracker\Core\Plugin();
            $plugin->init();
        }
    } catch (\Throwable $e) {
        if ((bool) get_option('iw8_wa_debug', false) && function_exists('error_log')) {
            error_log('IW8 WaClickTracker Plugin Error: ' . $e->getMessage());
        }
        add_action('admin_notices', 'iw8_wa_click_tracker_init_error_notice');
    }
}
function iw8_wa_click_tracker_init_error_notice()
{
    echo '<div class="notice notice-error"><p>';
    echo esc_html__('Erro ao inicializar IW8 – Rastreador de Cliques WhatsApp.', 'iw8-wa-click-tracker');
    echo '</p></div>';
}
add_action('init', 'iw8_wa_click_tracker_init', 20);

/** === Ativação (único hook) — cria/atualiza DB e testa inserção/limpeza === */
function iw8_wa_click_tracker_activate()
{
    $log_activation = function (string $message, bool $is_error = false): void {
        $prefix = $is_error ? 'ERROR' : 'INFO';
        $timestamp = function_exists('current_time') ? current_time('Y-m-d H:i:s') : gmdate('Y-m-d H:i:s');
        $line = sprintf('[%s] [IW8_WA_CLICK_TRACKER] %s: %s', $timestamp, $prefix, $message);

        if ((bool) get_option('iw8_wa_debug', false) && function_exists('error_log')) {
            error_log($line);
        }

        // Fallback: tenta gravar em logs/plugin.log se possível.
        try {
            $logsDir = IW8_WA_CLICK_TRACKER_PLUGIN_DIR . 'logs/';
            if (!is_dir($logsDir)) {
                @wp_mkdir_p($logsDir);
            }
            if (is_dir($logsDir) && is_writable($logsDir)) {
                @file_put_contents($logsDir . 'plugin.log', $line . "\n", FILE_APPEND | LOCK_EX);
            }
        } catch (\Throwable $t) {
            // Ignora: já logamos via error_log quando habilitado.
        }
    };

    try {
        $log_activation('Iniciando ativação do plugin');

        // Autoload de includes (necessário para migrações e classes legadas).
        $incAutoload = IW8_WA_CLICK_TRACKER_PLUGIN_DIR . 'includes/autoload.php';
        if (!is_readable($incAutoload)) {
            throw new \Exception('Autoloader (includes/autoload.php) não encontrado');
        }
        require_once $incAutoload;
        $log_activation('Autoloader (includes) carregado');

        // Executa migrações (cria/atualiza {prefix}iw8_wa_clicks e migra legado se aplicável).
        if (function_exists('iw8_wa_run_migrations')) {
            iw8_wa_run_migrations();
            $log_activation('Migrações executadas');
        } else {
            throw new \Exception('Função de migração ausente (iw8_wa_run_migrations)');
        }

        // Confirma existência de tabela nova ou antiga.
        global $wpdb;
        $tableNew = $wpdb->prefix . 'iw8_wa_clicks';
        $tableOld = $wpdb->prefix . 'wa_clicks';
        $existsNew = (bool) $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $tableNew));
        $existsOld = (bool) $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $tableOld));

        if (!$existsNew && !$existsOld) {
            throw new \Exception('Tabela não foi criada no banco de dados');
        }
        $log_activation('Verificação de tabela no banco: OK');

        // Atualiza versão de schema.
        update_option('iw8_wa_db_version', IW8_WA_DB_VERSION);

        // Teste de inserção usando o repositório legado (includes/Database/ClickRepository.php).
        if (class_exists('\IW8\WaClickTracker\Database\ClickRepository')) {
            $test = [
                'url'          => 'https://api.whatsapp.com/send?phone=1234567890&text=test',
                'page_url'     => home_url('/'),
                'element_tag'  => 'ACTIVATION_TEST',
                'element_text' => 'Teste de ativação',
                'user_agent'   => 'WP-Plugin-Activation',
                'clicked_at'   => current_time('mysql'),
            ];
            /** @var \IW8\WaClickTracker\Database\ClickRepository $repo */
            $repo = new \IW8\WaClickTracker\Database\ClickRepository();
            $insertId = $repo->insertClick($test);

            if (is_wp_error($insertId)) {
                throw new \Exception('Falha no teste de inserção: ' . $insertId->get_error_message());
            }
            $log_activation('Teste de inserção: OK (ID: ' . (int) $insertId . ')');

            // Limpeza do registro de teste na tabela realmente usada.
            $repoTable = method_exists($repo, 'getTableName') ? $repo->getTableName() : ($existsNew ? $tableNew : $tableOld);
            $wpdb->delete($repoTable, ['id' => (int) $insertId]);
        }

        $log_activation('Plugin ativado com sucesso - todos os testes passaram');
    } catch (\Throwable $e) {
        $ctx = [
            'file'                => $e->getFile(),
            'line'                => $e->getLine(),
            'php_version'         => PHP_VERSION,
            'wp_version'          => function_exists('get_bloginfo') ? get_bloginfo('version') : 'N/A',
            'plugin_dir'          => IW8_WA_CLICK_TRACKER_PLUGIN_DIR,
            'plugin_dir_writable' => is_writable(IW8_WA_CLICK_TRACKER_PLUGIN_DIR),
        ];
        $log_activation('ERRO FATAL na ativação: ' . $e->getMessage(), true);
        $log_activation('Contexto do erro: ' . wp_json_encode($ctx), true);

        // Propaga para que o WP mostre o erro de ativação.
        throw $e;
    }
}
register_activation_hook(__FILE__, 'iw8_wa_click_tracker_activate');

/** === Desinstalação (placeholder seguro) === */
function iw8_wa_click_tracker_uninstall()
{
    if (!defined('WP_UNINSTALL_PLUGIN')) {
        exit;
    }
    if (!current_user_can('activate_plugins')) {
        return;
    }
    if ((bool) get_option('iw8_wa_debug', false) && function_exists('error_log')) {
        error_log('IW8_WA_CLICK_TRACKER: Plugin desinstalado');
    }
    // TODO: remover opções se desejar; por padrão manter tabelas.
}
register_uninstall_hook(__FILE__, 'iw8_wa_click_tracker_uninstall');
