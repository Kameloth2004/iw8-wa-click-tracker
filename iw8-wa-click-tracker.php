<?php

/**
 * Plugin Name: IW8 – Rastreador de Cliques WhatsApp
 * Plugin URI: https://github.com/iw8/iw8-wa-click-tracker
 * Description: Plugin para rastrear cliques em links do WhatsApp e gerar relatórios detalhados
 * Version:  1.4.0
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
 * @version 1.3.0
 * @author Adriano Marques
 * @license GPL v2 or later
 */

// Prevenir acesso direto
if (!defined('ABSPATH')) {
    exit;
}

// === Composer autoload (precisa vir ANTES de qualquer uso de classes do src/) ===
if (file_exists(__DIR__ . '/vendor/autoload.php')) {
    require_once __DIR__ . '/vendor/autoload.php';
} else {
    // Log simples para diagnosticar no ambiente Local (Logs > PHP)
    if (function_exists('error_log')) {
        error_log('IW8_WA: vendor/autoload.php não encontrado');
    }
}

// === Carregamento direto das classes REST/Security (bypass do Composer no dev local) ===
require_once __DIR__ . '/src/Support/Env.php';
require_once __DIR__ . '/src/Security/HttpsEnforcer.php';
require_once __DIR__ . '/src/Security/TokenAuthenticator.php';
require_once __DIR__ . '/src/Services/TimeProvider.php';
require_once __DIR__ . '/src/Services/LimitsProvider.php';
require_once __DIR__ . '/src/Rest/PingController.php';
require_once __DIR__ . '/src/Rest/ApiRegistrar.php';
require_once __DIR__ . '/src/Http/ErrorFactory.php';
require_once __DIR__ . '/src/Http/JsonResponse.php';
require_once __DIR__ . '/src/Validation/CursorCodec.php';
require_once __DIR__ . '/src/Validation/RequestValidator.php';
require_once __DIR__ . '/src/Rest/ClicksController.php';
require_once __DIR__ . '/src/Repositories/ClickRepository.php';
require_once __DIR__ . '/includes/install/db-migrations.php';

// Roda ao ativar
register_activation_hook(__FILE__, 'iw8_wa_run_migrations');

// Roda se a versão de DB estiver desatualizada
add_action('admin_init', function () {
    $current = get_option('iw8_wa_db_version');
    $target  = '2025-08-29-1';
    if ($current !== $target) {
        iw8_wa_run_migrations();
    }
});


// === Registra as rotas REST no init do WP REST ===
add_action('rest_api_init', function () {
    // Se por algum motivo o autoload não carregou, registramos o log e abortamos
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

// === Auto-update via GitHub (PUC v5) – Bootstrap ===

// 2.1) Carregar autoloader do Composer
$iw8_wa_click_tracker_autoload = __DIR__ . '/vendor/autoload.php';
if (file_exists($iw8_wa_click_tracker_autoload)) {
    require_once $iw8_wa_click_tracker_autoload;
}

// 2.2) Registrar o update checker
if (class_exists(\YahnisElsts\PluginUpdateChecker\v5\PucFactory::class)) {
    $iw8_wa_update_checker = \YahnisElsts\PluginUpdateChecker\v5\PucFactory::buildUpdateChecker(
        'https://github.com/kameloth2004/iw8-wa-click-tracker',
        __FILE__,
        'iw8-wa-click-tracker'
    );

    $iw8_wa_update_checker->setBranch('main');

    $api = $iw8_wa_update_checker->getVcsApi();
    if ($api !== null) {
        $api->enableReleaseAssets();
    }
}

// Definir constantes do plugin
define('IW8_WA_CLICK_TRACKER_VERSION', '1.4.0');
define('IW8_WA_CLICK_TRACKER_PLUGIN_SLUG', 'iw8-wa-click-tracker');
define('IW8_WA_CLICK_TRACKER_PLUGIN_FILE', __FILE__);
define('IW8_WA_CLICK_TRACKER_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('IW8_WA_CLICK_TRACKER_PLUGIN_URL', plugin_dir_url(__FILE__));
define('IW8_WA_CLICK_TRACKER_PLUGIN_BASENAME', plugin_basename(__FILE__));
define('IW8_WA_CLICK_TRACKER_TEXT_DOMAIN', 'iw8-wa-click-tracker');
define('IW8_WA_DB_VERSION', '1.1');

// Carregar autoloader
require_once IW8_WA_CLICK_TRACKER_PLUGIN_DIR . 'includes/autoload.php';

add_action('init', static function () {
    if (is_admin()) {
        \IW8\WaClickTracker\Admin\Actions\ExportCsvHandler::register();
    }
}, 5);

// Hook de ativação
function iw8_wa_click_tracker_activate()
{
    // Função de logging de fallback para erros fatais (ANTES de qualquer dependência)
    $log_activation = function ($message, $is_error = false) {
        $prefix = $is_error ? 'ERROR' : 'INFO';
        $timestamp = function_exists('current_time') ? current_time('Y-m-d H:i:s') : date('Y-m-d H:i:s');
        $log_message = sprintf(
            '[%s] [IW8_WA_CLICK_TRACKER] %s: %s',
            $timestamp,
            $prefix,
            $message
        );

        // SEMPRE logar via error_log primeiro (garantia absoluta)
        if ((bool) get_option('iw8_wa_debug', false) && function_exists('error_log')) {
            error_log($log_message);
        }

        // Tentar usar a classe Logger se disponível (opcional)
        if (class_exists('\IW8\WaClickTracker\Core\Logger')) {
            try {
                if ($is_error) {
                    \IW8\WaClickTracker\Core\Logger::activationError($message);
                } else {
                    \IW8\WaClickTracker\Core\Logger::activation($message);
                }
            } catch (Exception $logger_error) {
                // Se Logger falhar, continuar com error_log
                if ((bool) get_option('iw8_wa_debug', false) && function_exists('error_log')) {
                    error_log('IW8_WA_CLICK_TRACKER FILE WRITE ERROR: ' . $logger_error->getMessage());
                }
            }
        }

        // Tentar escrever no arquivo de log diretamente (fallback adicional)
        try {
            $logs_dir = IW8_WA_CLICK_TRACKER_PLUGIN_DIR . 'logs/';
            $log_file = $logs_dir . 'plugin.log';

            if (is_dir($logs_dir) && is_writable($logs_dir)) {
                $formatted_message = $log_message . "\n";
                file_put_contents($log_file, $formatted_message, FILE_APPEND | LOCK_EX);
            }
        } catch (Exception $file_error) {
            // Se escrita em arquivo falhar, pelo menos error_log funcionou
            if ((bool) get_option('iw8_wa_debug', false) && function_exists('error_log')) {
                error_log('IW8_WA_CLICK_TRACKER FATAL ERROR: ' . $file_error->getMessage());
            }
        }
    };

    try {
        // Log de início da ativação (IMEDIATO, sem dependências)
        $log_activation('Iniciando ativação do plugin');

        // Verificar se autoloader existe
        if (!file_exists(IW8_WA_CLICK_TRACKER_PLUGIN_DIR . 'includes/autoload.php')) {
            throw new Exception('Autoloader não encontrado: ' . IW8_WA_CLICK_TRACKER_PLUGIN_DIR . 'includes/autoload.php');
        }

        // Verificar se diretório includes existe
        if (!is_dir(IW8_WA_CLICK_TRACKER_PLUGIN_DIR . 'includes/')) {
            throw new Exception('Diretório includes não encontrado');
        }

        // Verificar permissões de escrita para logs
        $logs_dir = IW8_WA_CLICK_TRACKER_PLUGIN_DIR . 'logs/';
        if (!is_dir($logs_dir)) {
            if (!wp_mkdir_p($logs_dir)) {
                $log_activation('Aviso: Não foi possível criar diretório de logs', true);
            }
        }

        if (is_dir($logs_dir) && !is_writable($logs_dir)) {
            $log_activation('Aviso: Diretório de logs não tem permissão de escrita', true);
        }

        // Carregar autoloader (redundante se já carregado no topo, mas inofensivo por ser require_once)
        require_once IW8_WA_CLICK_TRACKER_PLUGIN_DIR . 'includes/autoload.php';
        $log_activation('Autoloader carregado com sucesso');

        // Verificar se classes essenciais existem
        $essential_classes = [
            '\IW8\WaClickTracker\Database\TableClicks',
            '\IW8\WaClickTracker\Core\Versions',
            '\IW8\WaClickTracker\Core\Logger',
        ];

        foreach ($essential_classes as $class) {
            if (!class_exists($class)) {
                throw new Exception("Classe essencial não encontrada: {$class}");
            }
        }

        $log_activation('Todas as classes essenciais carregadas');

        // Criar tabela de cliques
        $table_clicks = new \IW8\WaClickTracker\Database\TableClicks();
        $table_created = $table_clicks->create_table();

        if (!$table_created) {
            throw new Exception('Falha ao criar tabela na ativação');
        }

        $log_activation('Tabela criada com sucesso');

        // Atualizar versão do schema
        update_option('iw8_wa_db_version', IW8_WA_DB_VERSION);

        // Executar upgrades de versão
        $versions = new \IW8\WaClickTracker\Core\Versions();
        $versions->maybe_upgrade();

        $log_activation('Upgrades de versão executados');

        // Verificar se tabela foi realmente criada
        global $wpdb;
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$wpdb->prefix}wa_clicks'");
        if (!$table_exists) {
            throw new Exception('Tabela não foi criada no banco de dados');
        }

        $log_activation('Verificação de tabela no banco: OK');

        // Testar inserção de registro
        $test_data = [
            'url'          => 'https://api.whatsapp.com/send?phone=1234567890&text=test',
            'page_url'     => home_url('/'),
            'element_tag'  => 'ACTIVATION_TEST',
            'element_text' => 'Teste de ativação',
            'user_agent'   => 'WP-Plugin-Activation',
            'clicked_at'   => current_time('mysql'),
        ];

        $repository  = new \IW8\WaClickTracker\Database\ClickRepository();
        $test_result = $repository->insertClick($test_data);

        if (is_wp_error($test_result)) {
            throw new Exception('Falha no teste de inserção: ' . $test_result->get_error_message());
        }

        $log_activation('Teste de inserção: OK (ID: ' . $test_result . ')');

        // Limpar registro de teste
        $wpdb->delete($wpdb->prefix . 'wa_clicks', ['id' => $test_result]);

        $log_activation('Plugin ativado com sucesso - todos os testes passaram');
    } catch (Exception $e) {
        $error_context = [
            'file'                 => $e->getFile(),
            'line'                 => $e->getLine(),
            'trace'                => $e->getTraceAsString(),
            'php_version'          => PHP_VERSION,
            'wp_version'           => function_exists('get_bloginfo') ? get_bloginfo('version') : 'N/A',
            'plugin_dir'           => IW8_WA_CLICK_TRACKER_PLUGIN_DIR,
            'plugin_dir_writable'  => is_writable(IW8_WA_CLICK_TRACKER_PLUGIN_DIR),
            'wpdb_prefix'          => isset($wpdb) ? $wpdb->prefix : 'N/A',
            'memory_limit'         => ini_get('memory_limit'),
            'max_execution_time'   => ini_get('max_execution_time'),
        ];

        $log_activation('ERRO FATAL na ativação: ' . $e->getMessage(), true);
        $log_activation('Contexto do erro: ' . json_encode($error_context), true);

        // Log adicional via error_log para garantir (múltiplas camadas)
        if ((bool) get_option('iw8_wa_debug', false) && function_exists('error_log')) {
            error_log('IW8_WA_CLICK_TRACKER FATAL ERROR: ' . $e->getMessage());
        }
        if ((bool) get_option('iw8_wa_debug', false) && function_exists('error_log')) {
            error_log('IW8_WA_CLICKTRACKER ERROR CONTEXT: ' . json_encode($error_context));
        }

        // Re-throw para WordPress mostrar erro na interface
        throw $e;
    }
}

// Registrar hook de ativação
register_activation_hook(__FILE__, 'iw8_wa_click_tracker_activate');

// Hook de desinstalação
function iw8_wa_click_tracker_uninstall()
{
    // Verificar se é uma desinstalação real
    if (!defined('WP_UNINSTALL_PLUGIN')) {
        exit;
    }

    // Verificar permissões
    if (!current_user_can('activate_plugins')) {
        return;
    }

    // Log da desinstalação
    if ((bool) get_option('iw8_wa_debug', false) && function_exists('error_log')) {
        error_log('IW8_WA_CLICK_TRACKER: Plugin sendo desinstalado');
    }

    // TODO: Implementar lógica de desinstalação
    // - Remover opções do banco
    // - Manter tabelas por padrão (configurável)
    // - Limpar arquivos temporários se necessário
}

// Registrar hook de desinstalação com função nomeada
register_uninstall_hook(__FILE__, 'iw8_wa_click_tracker_uninstall');

// Função para verificar requisitos mínimos
function iw8_wa_click_tracker_check_requirements()
{
    // Verificar requisitos mínimos
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

// Função para notice de versão PHP
function iw8_wa_click_tracker_php_version_notice()
{
    echo '<div class="notice notice-error"><p>';
    echo sprintf(
        __('IW8 – Rastreador de Cliques WhatsApp requer PHP 7.4 ou superior. Sua versão atual é %s.', 'iw8-wa-click-tracker'),
        PHP_VERSION
    );
    echo '</p></div>';
}

// Função para notice de versão WordPress
function iw8_wa_click_tracker_wp_version_notice()
{
    echo '<div class="notice notice-error"><p>';
    echo __('IW8 – Rastreador de Cliques WhatsApp requer WordPress 6.0 ou superior.', 'iw8-wa-click-tracker');
    echo '</p></div>';
}

// Função para inicializar o plugin
function iw8_wa_click_tracker_init()
{
    // Verificar se o WordPress está completamente carregado
    if (!function_exists('add_action') || !function_exists('current_user_can') || !function_exists('get_option')) {
        return;
    }

    // Carregar text domain
    load_plugin_textdomain(
        IW8_WA_CLICK_TRACKER_TEXT_DOMAIN,
        false,
        dirname(IW8_WA_CLICK_TRACKER_PLUGIN_BASENAME) . '/languages'
    );

    // Inicializar plugin principal
    try {
        $plugin = new \IW8\WaClickTracker\Core\Plugin();
        $plugin->init();
    } catch (Exception $e) {
        // Log do erro (sem saída direta)
        if ((bool) get_option('iw8_wa_debug', false) && function_exists('error_log')) {
            error_log('IW8 WaClickTracker Plugin Error: ' . $e->getMessage());
        }

        add_action('admin_notices', 'iw8_wa_click_tracker_init_error_notice');
    }
};

// Função para notice de erro de inicialização
function iw8_wa_click_tracker_init_error_notice()
{
    echo '<div class="notice notice-error"><p>';
    echo __('Erro ao inicializar IW8 – Rastreador de Cliques WhatsApp.', 'iw8-wa-click-tracker');
    echo '</p></div>';
}

add_action('admin_init', function () {
    $cur = get_option('iw8_wa_db_version');
    if ($cur !== IW8_WA_DB_VERSION) {
        // Garante/atualiza a tabela (dbDelta aplica diffs, inclusive índices novos)
        $table_clicks = new \IW8\WaClickTracker\Database\TableClicks();
        $table_clicks->ensure_table(); // ou ensure_schema(), se você nomeou assim

        update_option('iw8_wa_db_version', IW8_WA_DB_VERSION);
    }
});

// Inicializar o plugin apenas quando o WordPress estiver pronto
add_action('init', 'iw8_wa_click_tracker_init', 20);

add_action('rest_api_init', function () {
    // Autoloader do Composer já deve estar ativo anteriormente no bootstrap.
    if (!class_exists(\IW8\WA\Rest\ApiRegistrar::class)) {
        return; // segurança em ambiente de dev
    }
    $registrar = new \IW8\WA\Rest\ApiRegistrar();
    $registrar->register();
});
