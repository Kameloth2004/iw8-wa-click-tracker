<?php
/**
 * Classe para logging e debug do plugin
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
 * Classe Logger
 */
class Logger
{
    /**
     * Níveis de log
     */
    const LEVEL_ERROR = 'ERROR';
    const LEVEL_WARNING = 'WARNING';
    const LEVEL_INFO = 'INFO';
    const LEVEL_DEBUG = 'DEBUG';

    /**
     * Prefixo para logs do plugin
     */
    const LOG_PREFIX = 'IW8_WA_CLICK_TRACKER';

    /**
     * Log de erro
     *
     * @param string $message Mensagem de erro
     * @param array $context Contexto adicional
     * @return void
     */
    public static function error($message, $context = [])
    {
        self::log(self::LEVEL_ERROR, $message, $context);
    }

    /**
     * Log de aviso
     *
     * @param string $message Mensagem de aviso
     * @param array $context Contexto adicional
     * @return void
     */
    public static function warning($message, $context = [])
    {
        self::log(self::LEVEL_WARNING, $message, $context);
    }

    /**
     * Log de informação
     *
     * @param string $message Mensagem informativa
     * @param array $context Contexto adicional
     * @return void
     */
    public static function info($message, $context = [])
    {
        self::log(self::LEVEL_INFO, $message, $context);
    }

    /**
     * Log de debug
     *
     * @param string $message Mensagem de debug
     * @param array $context Contexto adicional
     * @return void
     */
    public static function debug($message, $context = [])
    {
        // Só logar debug se estiver habilitado
        if (self::isDebugEnabled()) {
            self::log(self::LEVEL_DEBUG, $message, $context);
        }
    }

    /**
     * Método principal de logging
     *
     * @param string $level Nível do log
     * @param string $message Mensagem
     * @param array $context Contexto
     * @return void
     */
    private static function log($level, $message, $context = [])
    {
        // Formatar mensagem
        $formatted_message = self::formatMessage($level, $message, $context);
        
        // Log no WordPress (se habilitado)
        if (defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
            error_log($formatted_message);
        }
        
        // Log no sistema (fallback)
        if (function_exists('error_log')) {
            error_log($formatted_message);
        }
        
        // Log personalizado do plugin (se diretório existir)
        self::writeToPluginLog($formatted_message);
    }

    /**
     * Formatar mensagem de log
     *
     * @param string $level Nível
     * @param string $message Mensagem
     * @param array $context Contexto
     * @return string
     */
    private static function formatMessage($level, $message, $context = [])
    {
        $timestamp = current_time('Y-m-d H:i:s');
        $formatted = sprintf(
            '[%s] [%s] %s: %s',
            $timestamp,
            self::LOG_PREFIX,
            $level,
            $message
        );
        
        // Adicionar contexto se existir
        if (!empty($context)) {
            $formatted .= ' | Context: ' . json_encode($context);
        }
        
        return $formatted;
    }

    /**
     * Escrever no log personalizado do plugin
     *
     * @param string $message Mensagem formatada
     * @return void
     */
    private static function writeToPluginLog($message)
    {
        $log_dir = IW8_WA_CLICK_TRACKER_PLUGIN_DIR . 'logs/';
        $log_file = $log_dir . 'plugin.log';
        
        // Criar diretório se não existir
        if (!is_dir($log_dir)) {
            wp_mkdir_p($log_dir);
        }
        
        // Verificar se consegue escrever
        if (!is_writable($log_dir) && !is_writable($log_file)) {
            return;
        }
        
        // Adicionar quebra de linha
        $message .= "\n";
        
        // Escrever no arquivo
        file_put_contents($log_file, $message, FILE_APPEND | LOCK_EX);
    }

    /**
     * Verificar se debug está habilitado
     *
     * @return bool
     */
    private static function isDebugEnabled()
    {
        // Verificar se debug está habilitado no plugin
        $plugin_debug = get_option('iw8_wa_debug', false);
        
        // Verificar se debug global está habilitado
        $wp_debug = defined('WP_DEBUG') && WP_DEBUG;
        
        return $plugin_debug || $wp_debug;
    }

    /**
     * Log de ativação do plugin
     *
     * @param string $message Mensagem
     * @param array $context Contexto
     * @return void
     */
    public static function activation($message, $context = [])
    {
        self::info("ACTIVATION: {$message}", $context);
    }

    /**
     * Log de erro de ativação
     *
     * @param string $message Mensagem
     * @param array $context Contexto
     * @return void
     */
    public static function activationError($message, $context = [])
    {
        self::error("ACTIVATION ERROR: {$message}", $context);
    }

    /**
     * Log de criação de tabela
     *
     * @param string $message Mensagem
     * @param array $context Contexto
     * @return void
     */
    public static function database($message, $context = [])
    {
        self::info("DATABASE: {$message}", $context);
    }

    /**
     * Log de erro de banco de dados
     *
     * @param string $message Mensagem
     * @param array $context Contexto
     * @return void
     */
    public static function databaseError($message, $context = [])
    {
        self::error("DATABASE ERROR: {$message}", $context);
    }

    /**
     * Log de upgrade de versão
     *
     * @param string $message Mensagem
     * @param array $context Contexto
     * @return void
     */
    public static function upgrade($message, $context = [])
    {
        self::info("UPGRADE: {$message}", $context);
    }

    /**
     * Log de erro de upgrade
     *
     * @param string $message Mensagem
     * @param array $context Contexto
     * @return void
     */
    public static function upgradeError($message, $context = [])
    {
        self::error("UPGRADE ERROR: {$message}", $context);
    }

    /**
     * Log de erro fatal (para problemas críticos)
     *
     * @param string $message Mensagem
     * @param array $context Contexto
     * @return void
     */
    public static function fatal($message, $context = [])
    {
        // Log imediato via error_log para garantir
        $fatal_message = "IW8_WA_CLICK_TRACKER FATAL: {$message}";
        if (!empty($context)) {
            $fatal_message .= " | Context: " . json_encode($context);
        }
        error_log($fatal_message);
        
        // Tentar log via sistema normal
        self::error("FATAL: {$message}", $context);
    }

    /**
     * Log de problema de inicialização
     *
     * @param string $message Mensagem
     * @param array $context Contexto
     * @return void
     */
    public static function initialization($message, $context = [])
    {
        self::error("INITIALIZATION ERROR: {$message}", $context);
    }

    /**
     * Log de problema de autoload
     *
     * @param string $message Mensagem
     * @param array $context Contexto
     * @return void
     */
    public static function autoload($message, $context = [])
    {
        self::error("AUTOLOAD ERROR: {$message}", $context);
    }

    /**
     * Log de problema de permissões
     *
     * @param string $message Mensagem
     * @param array $context Contexto
     * @return void
     */
    public static function permissions($message, $context = [])
    {
        self::warning("PERMISSIONS: {$message}", $context);
    }

    /**
     * Log de problema de sistema
     *
     * @param string $message Mensagem
     * @param array $context Contexto
     * @return void
     */
    public static function system($message, $context = [])
    {
        self::info("SYSTEM: {$message}", $context);
    }

    /**
     * Verificar se o sistema de logging está funcionando
     *
     * @return bool
     */
    public static function isWorking()
    {
        try {
            // Testar escrita no arquivo de log
            $test_message = 'IW8_WA_CLICK_TRACKER LOG TEST: ' . microtime(true);
            self::info($test_message);
            
            // Verificar se a mensagem foi escrita
            $log_file = self::getPluginLogFile();
            if ($log_file && file_exists($log_file)) {
                $content = file_get_contents($log_file);
                return strpos($content, $test_message) !== false;
            }
            
            return false;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Log de diagnóstico do sistema
     *
     * @return void
     */
    public static function logSystemDiagnostics()
    {
        $diagnostics = [
            'php_version' => PHP_VERSION,
            'wp_version' => function_exists('get_bloginfo') ? get_bloginfo('version') : 'N/A',
            'plugin_dir' => defined('IW8_WA_CLICK_TRACKER_PLUGIN_DIR') ? IW8_WA_CLICK_TRACKER_PLUGIN_DIR : 'N/A',
            'plugin_dir_writable' => defined('IW8_WA_CLICK_TRACKER_PLUGIN_DIR') ? is_writable(IW8_WA_CLICK_TRACKER_PLUGIN_DIR) : 'N/A',
            'logs_dir_exists' => defined('IW8_WA_CLICK_TRACKER_PLUGIN_DIR') ? is_dir(IW8_WA_CLICK_TRACKER_PLUGIN_DIR . 'logs/') : 'N/A',
            'logs_dir_writable' => defined('IW8_WA_CLICK_TRACKER_PLUGIN_DIR') ? is_writable(IW8_WA_CLICK_TRACKER_PLUGIN_DIR . 'logs/') : 'N/A',
            'memory_limit' => ini_get('memory_limit'),
            'max_execution_time' => ini_get('max_execution_time'),
            'error_reporting' => error_reporting(),
            'log_errors' => ini_get('log_errors'),
            'error_log' => ini_get('error_log'),
            'wp_debug' => defined('WP_DEBUG') ? WP_DEBUG : 'N/A',
            'wp_debug_log' => defined('WP_DEBUG_LOG') ? WP_DEBUG_LOG : 'N/A'
        ];
        
        self::info('System diagnostics', $diagnostics);
    }

    /**
     * Obter arquivo de log do plugin
     *
     * @return string|false Caminho do arquivo ou false se não existir
     */
    public static function getPluginLogFile()
    {
        $log_file = IW8_WA_CLICK_TRACKER_PLUGIN_DIR . 'logs/plugin.log';
        return file_exists($log_file) ? $log_file : false;
    }

    /**
     * Limpar logs antigos (manter apenas últimos 7 dias)
     *
     * @return void
     */
    public static function cleanupOldLogs()
    {
        $log_file = self::getPluginLogFile();
        if (!$log_file) {
            return;
        }
        
        $max_age = 7 * 24 * 60 * 60; // 7 dias em segundos
        $file_time = filemtime($log_file);
        
        if ((time() - $file_time) > $max_age) {
            // Manter apenas as últimas 1000 linhas
            $lines = file($log_file);
            if (count($lines) > 1000) {
                $recent_lines = array_slice($lines, -1000);
                file_put_contents($log_file, implode('', $recent_lines));
            }
        }
    }
}
