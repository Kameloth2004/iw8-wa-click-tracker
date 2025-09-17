<?php

/**
 * Gerencia atualizações automáticas via GitHub (Plugin Update Checker)
 *
 * @package IW8_WaClickTracker\Core
 * @version 1.4.1
 */

declare(strict_types=1);

namespace IW8\WaClickTracker\Core;

if (!defined('ABSPATH')) {
    exit;
}

final class Updater
{
    /** @var bool Evita inicialização dupla */
    private static $booted = false;

    /** @var object|null Instância do UpdateChecker */
    private static $checker = null;

    /**
     * Inicializa o Update Checker (idempotente).
     * Chame uma única vez, por exemplo no arquivo principal:
     *
     *   add_action('admin_init', ['IW8\WaClickTracker\Core\Updater', 'init']);
     */
    public static function init(): void
    {
        if (self::$booted) {
            return;
        }
        self::$booted = true;

        try {
            if (!self::loadPuc()) {
                // Não fatal: apenas não inicializa updates
                if (function_exists('error_log')) {
                    error_log('IW8_WA Updater: PUC não disponível, updates desativados.');
                }
                return;
            }

            // Descobre o arquivo principal do plugin com segurança
            $pluginFile = defined('IW8_WA_CLICK_TRACKER_PLUGIN_FILE')
                ? IW8_WA_CLICK_TRACKER_PLUGIN_FILE
                : (defined('IW8_WA_CLICK_TRACKER_PLUGIN_DIR')
                    ? IW8_WA_CLICK_TRACKER_PLUGIN_DIR . 'iw8-wa-click-tracker.php'
                    : null);

            if (!is_string($pluginFile) || $pluginFile === '' || !file_exists($pluginFile)) {
                if (function_exists('error_log')) {
                    error_log('IW8_WA Updater: plugin file não encontrado para calcular slug.');
                }
                return;
            }

            // Slug correto SEMPRE via plugin_basename (evita erro de slug duplicado)
            $slug = plugin_basename($pluginFile); // ex: "iw8-wa-click-tracker/iw8-wa-click-tracker.php"

            // Monte o checker apontando para o repositório GitHub (PUC lida com tags/releases)
            self::$checker = \Puc_v5_Factory::buildUpdateChecker(
                'https://github.com/Kameloth2004/iw8-wa-click-tracker',
                $pluginFile,
                $slug
            );

            // Branch padrão
            if (method_exists(self::$checker, 'setBranch')) {
                self::$checker->setBranch('main');
            }

            // Usar assets de release (ZIP anexado nas Releases do GitHub)
            if (method_exists(self::$checker, 'getVcsApi') && self::$checker->getVcsApi()) {
                self::$checker->getVcsApi()->enableReleaseAssets();
            }

            // Autenticação opcional (se usar repo privado ou limitar rate-limit)
            self::maybeConfigureAuth(self::$checker);

            if (function_exists('error_log')) {
                error_log('IW8_WA Updater: Update checker inicializado com sucesso.');
            }
        } catch (\Throwable $e) {
            if (function_exists('error_log')) {
                error_log('IW8_WA Updater ERROR: ' . $e->getMessage());
            }
        }
    }

    /**
     * Carrega o Plugin Update Checker v5.6.
     * Tenta primeiro via Composer (vendor/), depois fallback para pasta legada.
     */
    private static function loadPuc(): bool
    {
        // Já disponível?
        if (class_exists('\Puc_v5_Factory')) {
            return true;
        }

        // 1) Tentar via vendor (Composer)
        if (defined('IW8_WA_CLICK_TRACKER_PLUGIN_DIR')) {
            $vendorLoader = IW8_WA_CLICK_TRACKER_PLUGIN_DIR . 'vendor/yahnis-elsts/plugin-update-checker/load-v5p6.php';
            if (is_readable($vendorLoader)) {
                require_once $vendorLoader;
            }
            if (class_exists('\Puc_v5_Factory')) {
                return true;
            }
        }

        // 2) Fallback legado (se você mantiver a pasta antiga)
        if (defined('IW8_WA_CLICK_TRACKER_PLUGIN_DIR')) {
            $legacy1 = IW8_WA_CLICK_TRACKER_PLUGIN_DIR . 'plugin-update-checker/load-v5p6.php';
            $legacy2 = IW8_WA_CLICK_TRACKER_PLUGIN_DIR . 'plugin-update-checker/plugin-update-checker.php';
            if (is_readable($legacy1)) {
                require_once $legacy1;
            } elseif (is_readable($legacy2)) {
                require_once $legacy2;
            }
        }

        return class_exists('\Puc_v5_Factory');
    }

    /**
     * Define autenticação no checker se um token estiver disponível.
     * Suporta constante, env var ou option do WP.
     */
    private static function maybeConfigureAuth($checker): void
    {
        // Constante
        if (defined('IW8_WA_GH_TOKEN') && is_string(IW8_WA_GH_TOKEN) && IW8_WA_GH_TOKEN !== '') {
            if (method_exists($checker, 'setAuthentication')) {
                $checker->setAuthentication(IW8_WA_GH_TOKEN);
            }
            return;
        }

        // Variável de ambiente
        $envToken = getenv('IW8_WA_GH_TOKEN');
        if (is_string($envToken) && $envToken !== '') {
            if (method_exists($checker, 'setAuthentication')) {
                $checker->setAuthentication($envToken);
            }
            return;
        }

        // Option do WP (casos especiais)
        if (function_exists('get_option')) {
            $optToken = (string) get_option('iw8_wa_gh_token', '');
            if ($optToken !== '' && method_exists($checker, 'setAuthentication')) {
                $checker->setAuthentication($optToken);
            }
        }
    }

    /**
     * (Opcional) Expor checker para depuração.
     */
    public static function getChecker()
    {
        return self::$checker;
    }
}
