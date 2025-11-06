<?php

/**
 * Gerencia atualizações automáticas via GitHub (Plugin Update Checker)
 *
 * @package IW8_WaClickTracker\Core
 * @version 1.4.3
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
     * Dispare em admin_init:
     *   add_action('admin_init', ['IW8\WaClickTracker\Core\Updater', 'init']);
     */
    public static function init(): void
    {
        if (self::$booted) {
            return;
        }
        self::$booted = true;

        try {
            $factory = self::resolveFactory();
            if ($factory === null) {
                // Não fatal: apenas não inicializa updates
                if (function_exists('error_log')) {
                    error_log('IW8_WA Updater: Plugin Update Checker não encontrado (vendor/plugin-update-checker ausente?). Updates desativados.');
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

            // Monte o checker apontando para o repositório GitHub
            self::$checker = $factory('https://github.com/Kameloth2004/iw8-wa-click-tracker', $pluginFile, $slug);

            // Branch padrão
            if (method_exists(self::$checker, 'setBranch')) {
                self::$checker->setBranch('main');
            }

            // Usar assets de release (ZIP anexado nas Releases do GitHub)
            if (method_exists(self::$checker, 'getVcsApi') && self::$checker->getVcsApi()) {
                self::$checker->getVcsApi()->enableReleaseAssets();
            }

            // Auth opcional (repo privado / rate limit)
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
     * Resolve a factory do PUC, tentando:
     * 1) Namespaced (Composer): YahnisElsts\PluginUpdateChecker\v5\PucFactory
     * 2) Loader vendor: vendor/yahnis-elsts/plugin-update-checker/load-v5p6.php
     * 3) Fallback legado: plugin-update-checker/load-v5p6.php | plugin-update-checker.php
     *
     * @return callable|null fn(string $repoUrl, string $pluginFile, string $slug): object
     */
    private static function resolveFactory(): ?callable
    {
        // 1) Namespaced via Composer (recomendado)
        if (class_exists('\YahnisElsts\PluginUpdateChecker\v5\PucFactory')) {
            return static function (string $repoUrl, string $pluginFile, string $slug) {
                /** @var \YahnisElsts\PluginUpdateChecker\v5\PucFactory $nsFactory */
                $nsFactory = '\YahnisElsts\PluginUpdateChecker\v5\PucFactory';
                return $nsFactory::buildUpdateChecker($repoUrl, $pluginFile, $slug);
            };
        }

        // 2) Tentar carregar o loader do pacote no vendor/
        if (defined('IW8_WA_CLICK_TRACKER_PLUGIN_DIR')) {
            $vendorLoader = IW8_WA_CLICK_TRACKER_PLUGIN_DIR . 'vendor/yahnis-elsts/plugin-update-checker/load-v5p6.php';
            if (is_readable($vendorLoader)) {
                require_once $vendorLoader;
            }
        }
        if (class_exists('\Puc_v5_Factory')) {
            return static function (string $repoUrl, string $pluginFile, string $slug) {
                return \Puc_v5_Factory::buildUpdateChecker($repoUrl, $pluginFile, $slug);
            };
        }

        // 3) Fallback para pasta legada (se você mantiver no projeto)
        if (defined('IW8_WA_CLICK_TRACKER_PLUGIN_DIR')) {
            $legacy1 = IW8_WA_CLICK_TRACKER_PLUGIN_DIR . 'plugin-update-checker/load-v5p6.php';
            $legacy2 = IW8_WA_CLICK_TRACKER_PLUGIN_DIR . 'plugin-update-checker/plugin-update-checker.php';
            if (is_readable($legacy1)) {
                require_once $legacy1;
            } elseif (is_readable($legacy2)) {
                require_once $legacy2;
            }
        }
        if (class_exists('\Puc_v5_Factory')) {
            return static function (string $repoUrl, string $pluginFile, string $slug) {
                return \Puc_v5_Factory::buildUpdateChecker($repoUrl, $pluginFile, $slug);
            };
        }

        // Nada encontrado
        return null;
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
