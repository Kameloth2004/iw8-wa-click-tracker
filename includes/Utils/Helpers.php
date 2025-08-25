<?php
/**
 * Classe utilitária com funções auxiliares
 *
 * @package IW8_WaClickTracker\Utils
 * @version 1.3.0
 */

namespace IW8\WaClickTracker\Utils;

// Prevenir acesso direto
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Classe Helpers
 */
class Helpers
{
    /**
     * Construtor da classe
     */
    public function __construct()
    {
        // Classe utilitária, não requer inicialização
    }

    /**
     * Limitar texto por número de palavras
     *
     * @param string $str Texto a ser limitado
     * @param int $words Número máximo de palavras (padrão: 20)
     * @return string Texto limitado
     */
    public static function trim_text($str, $words = 20)
    {
        if (empty($str)) {
            return '';
        }
        
        return wp_trim_words($str, $words, '...');
    }

    /**
     * Formatar data/hora MySQL para exibição
     *
     * @param string $mysql Data/hora no formato MySQL
     * @return string Data/hora formatada
     */
    public static function format_datetime($mysql)
    {
        if (empty($mysql)) {
            return '-';
        }
        
        $timestamp = strtotime($mysql);
        if ($timestamp === false) {
            return $mysql;
        }
        
        return date_i18n('Y-m-d H:i', $timestamp);
    }

    /**
     * Verificar se telefone está configurado e é válido
     *
     * @return bool
     */
    public static function isPhoneConfigured()
    {
        $phone = get_option('iw8_wa_phone', '');
        if (empty($phone)) {
            return false;
        }
        
        // Normalizar e verificar comprimento
        $digits_only = preg_replace('/[^0-9]/', '', $phone);
        return strlen($digits_only) >= 10 && strlen($digits_only) <= 15;
    }

    /**
     * Obter telefone configurado (apenas dígitos)
     *
     * @return string
     */
    public static function getConfiguredPhone()
    {
        $phone = get_option('iw8_wa_phone', '');
        return preg_replace('/[^0-9]/', '', $phone);
    }

    /**
     * Gerar regex para filtro de URL baseado no telefone
     *
     * @param string $phone Telefone (apenas dígitos)
     * @return string Regex para MySQL
     */
    public static function generateUrlRegexp($phone)
    {
        if (empty($phone)) {
            return '';
        }
        
        // Escapar barras para MySQL REGEXP
        $phone_escaped = str_replace('/', '\\/', $phone);
        
        // Padrão para MySQL REGEXP
        return "phone=({$phone_escaped}|%2B{$phone_escaped}|\\+{$phone_escaped})|wa\\.me\\/({$phone_escaped}|%2B{$phone_escaped}|\\+{$phone_escaped})";
    }
}
