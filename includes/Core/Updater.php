<?php
/**
 * Classe para gerenciar atualizações automáticas via GitHub
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
 * Classe Updater
 */
class Updater
{
    /**
     * Construtor da classe
     */
    public function __construct()
    {
        // Classe utilitária, não requer inicialização
    }

    /**
     * Inicializar o sistema de atualizações
     *
     * @return void
     */
    public function init()
    {
        // Verificar se Plugin Update Checker está disponível
        if (!$this->isPucAvailable()) {
            return;
        }

        try {
            $this->setupUpdateChecker();
        } catch (\Exception $e) {
            // Log do erro (sem poluir)
            if (function_exists('error_log')) {
                error_log('IW8 WaClickTracker Updater Error: ' . $e->getMessage());
            }
        }
    }

    /**
     * Verificar se Plugin Update Checker está disponível
     *
     * @return bool
     */
    private function isPucAvailable()
    {
        // Verificar se a pasta plugin-update-checker existe
        $puc_path = IW8_WA_CLICK_TRACKER_PLUGIN_DIR . 'plugin-update-checker/';
        if (!is_dir($puc_path)) {
            return false;
        }

        // Verificar se a classe principal existe
        if (!class_exists('\Puc_v4_Factory')) {
            // Tentar carregar o autoloader
            $autoloader = $puc_path . 'plugin-update-checker.php';
            if (file_exists($autoloader)) {
                require_once $autoloader;
            }
        }

        return class_exists('\Puc_v4_Factory');
    }

    /**
     * Configurar o update checker
     *
     * @return void
     */
    private function setupUpdateChecker()
    {
        // Verificar se a classe existe após tentar carregar
        if (!class_exists('\Puc_v4_Factory')) {
            throw new \Exception('Plugin Update Checker não pôde ser carregado');
        }

        // Criar instância do update checker
        $updateChecker = \Puc_v4_Factory::buildUpdateChecker(
            'https://github.com/Kameloth2004/iw8-wa-click-tracker',
            IW8_WA_CLICK_TRACKER_PLUGIN_FILE,
            'iw8-wa-click-tracker'
        );

        // Configurar branch principal
        $updateChecker->setBranch('main');

        // Habilitar assets de release
        $updateChecker->getVcsApi()->enableReleaseAssets();

        // Configurar autenticação se token estiver disponível
        $this->setupAuthentication($updateChecker);

        // Log de sucesso (opcional)
        if (function_exists('error_log')) {
            error_log('IW8 WaClickTracker: Update checker configurado com sucesso');
        }
    }

    /**
     * Configurar autenticação se token estiver disponível
     *
     * @param object $updateChecker Instância do update checker
     * @return void
     */
    private function setupAuthentication($updateChecker)
    {
        // Verificar se há token definido como constante
        if (defined('IW8_WA_GH_TOKEN') && !empty(IW8_WA_GH_TOKEN)) {
            $updateChecker->setAuthentication(IW8_WA_GH_TOKEN);
            return;
        }

        // Verificar se há token em variável de ambiente
        $env_token = getenv('IW8_WA_GH_TOKEN');
        if (!empty($env_token)) {
            $updateChecker->setAuthentication($env_token);
            return;
        }

        // Verificar se há token em opção do WordPress (para casos especiais)
        $option_token = get_option('iw8_wa_gh_token', '');
        if (!empty($option_token)) {
            $updateChecker->setAuthentication($option_token);
            return;
        }
    }

    /**
     * Verificar se há atualizações disponíveis
     *
     * @return bool
     */
    public function hasUpdates()
    {
        if (!$this->isPucAvailable()) {
            return false;
        }

        // Esta verificação é feita automaticamente pelo PUC
        // Retornamos false para não interferir no fluxo padrão
        return false;
    }

    /**
     * Obter informações sobre atualizações
     *
     * @return array|false
     */
    public function getUpdateInfo()
    {
        if (!$this->isPucAvailable()) {
            return false;
        }

        // O PUC gerencia isso automaticamente
        // Retornamos false para não interferir
        return false;
    }
}
