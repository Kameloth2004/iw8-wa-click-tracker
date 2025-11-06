<?php
/**
 * Classe para gerenciar versões e upgrades do plugin
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
 * Classe Versions
 */
class Versions
{
    /**
     * Versão atual do schema do banco
     */
    const SCHEMA_VERSION = '1.0.0';
    
    /**
     * Nome da opção para armazenar versão do banco
     */
    const DB_VERSION_OPTION = 'iw8_wa_db_version';
    
    /**
     * Versão atual do banco
     *
     * @var string
     */
    private $current_version;

    /**
     * Construtor da classe
     */
    public function __construct()
    {
        $this->current_version = get_option(self::DB_VERSION_OPTION, '0.0.0');
    }

    /**
     * Verificar e executar upgrades necessários
     *
     * @return bool
     */
    public function maybe_upgrade()
    {
        // Se a versão atual for igual à versão do schema, não há upgrades necessários
        if (version_compare($this->current_version, self::SCHEMA_VERSION, '>=')) {
            return true;
        }
        
        // Executar migrações necessárias
        $result = $this->run_migrations();
        
        if ($result) {
            // Atualizar versão no banco
            update_option(self::DB_VERSION_OPTION, self::SCHEMA_VERSION);
            $this->current_version = self::SCHEMA_VERSION;
        }
        
        return $result;
    }

    /**
     * Executar migrações pendentes
     *
     * @return bool
     */
    private function run_migrations()
    {
        // Para esta versão inicial, apenas garantir que a tabela existe
        try {
            $table_clicks = new \IW8\WaClickTracker\Database\TableClicks();
            return $table_clicks->ensure_table();
        } catch (\Exception $e) {
            // Log do erro (sem poluir)
            if (function_exists('error_log')) {
                error_log('IW8 WaClickTracker Migration Error: ' . $e->getMessage());
            }
            return false;
        }
    }

    /**
     * Obter versão atual do banco
     *
     * @return string
     */
    public function get_current_version()
    {
        return $this->current_version;
    }

    /**
     * Obter versão do schema
     *
     * @return string
     */
    public function get_schema_version()
    {
        return self::SCHEMA_VERSION;
    }

    /**
     * Verificar se upgrade é necessário
     *
     * @return bool
     */
    public function needs_upgrade()
    {
        return version_compare($this->current_version, self::SCHEMA_VERSION, '<');
    }
}
