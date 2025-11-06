<?php

/**
 * Classe para verificar se URLs correspondem aos padrões de destino
 *
 * @package IW8_WaClickTracker\Frontend
 * @version 1.3.0
 */

namespace IW8\WaClickTracker\Frontend;

// Prevenir acesso direto
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Classe UrlMatcher
 */
class UrlMatcher
{
    /**
     * Construtor da classe
     */
    public function __construct()
    {
        // Classe utilitária, não requer inicialização
    }

    /**
     * Obter padrão regex para URLs do WhatsApp
     *
     * Retorna a expressão regular SEM delimitadores; o envoltório (#...#i) é feito em matchesTarget().
     *
     * Regras cobertas:
     *  - http/https://api.whatsapp.com/send|message com query contendo phone=... (em qualquer ordem)
     *  - http/https://wa.me/<phone>
     *  - http/https://api.whatsapp.com/message/<code>  (sem phone)
     *  - http/https://wa.me/message/<code>            (sem phone)
     *  - whatsapp://send?phone=...
     *
     * @param string $phone Número de telefone (apenas dígitos)
     * @return string Regex sem delimitadores
     */
    public function pattern($phone)
    {
        // Normalizar telefone (apenas dígitos)
        $phone = preg_replace('/\D+/', '', (string) $phone);
        if ($phone === '') {
            return '';
        }

        // Escape do telefone usando o MESMO delimitador (#) que será usado no preg_match
        $phone_escaped = preg_quote($phone, '#');

        // Padrões (sem âncoras aqui; elas entram no matchesTarget())
        $patterns = [
            // api.whatsapp.com/send|message com query contendo phone=... (em qualquer ordem)
            'https?://api\.whatsapp\.com/(?:send|message)(?:\?(?=[^#]*\bphone=(?:\+|%2B)?' . $phone_escaped . ')[^#]*)?',

            // wa.me/<phone>
            'https?://wa\.me/(?:\+|%2B)?' . $phone_escaped . '(?:\?[^#]*)?',

            // Códigos curtos (sem phone explícito)
            'https?://api\.whatsapp\.com/message/[^#\s]+',
            'https?://wa\.me/message/[^#\s]+',

            // Esquema nativo (mobile): whatsapp://send?phone=...
            'whatsapp://send(?:\?(?=[^#]*\bphone=(?:\+|%2B)?' . $phone_escaped . ')[^#]*)?',
        ];

        // Combinar padrões com OR lógico
        return '(?:' . implode('|', $patterns) . ')';
    }

    /**
     * Verificar se URL corresponde ao padrão de destino
     *
     * @param string $url   URL a ser verificada
     * @param string $phone Número de telefone para validação
     * @return bool
     */
    public function matchesTarget($url, $phone)
    {
        // Validações básicas
        if (empty($url) || empty($phone)) {
            return false;
        }

        // Normalizar telefone (apenas dígitos) e validar tamanho
        $digits = preg_replace('/\D+/', '', $phone);
        if (strlen($digits) < 10 || strlen($digits) > 15) {
            return false;
        }

        // 1) Tenta via regex (aceita http/https/whatsapp://; qualquer ordem de query, desde que contenha phone=)
        $p = preg_quote($digits, '#');
        $regex = '#^('
            // api.whatsapp.com/send|message com phone em QUALQUER ponto da query
            . 'https?://api\.whatsapp\.com/(?:send|message)(?:\?(?=[^#]*\bphone=(?:\+|%2B)?' . $p . ')[^#]*)?'
            . '|'
            // wa.me/<phone> (com + opcional) + query livre
            . 'https?://wa\.me/(?:\+|%2B)?' . $p . '(?:\?[^#]*)?'
            . '|'
            // api.whatsapp.com/message/<código>
            . 'https?://api\.whatsapp\.com/message/[^#\s]+'
            . '|'
            // wa.me/message/<código>
            . 'https?://wa\.me/message/[^#\s]+'
            . '|'
            // whatsapp://send com phone em qualquer ponto da query
            . 'whatsapp://send(?:\?(?=[^#]*\bphone=(?:\+|%2B)?' . $p . ')[^#]*)?'
            . ')$#i';

        $ok = @preg_match($regex, $url) === 1;
        if ($ok) {
            return true;
        }

        // 2) Fallback robusto: parse_url + parse_str (ordem de parâmetros, http/https, etc.)
        $parts = @parse_url($url);
        if (!$parts) {
            return false;
        }
        $scheme = isset($parts['scheme']) ? strtolower($parts['scheme']) : '';
        $host   = isset($parts['host']) ? strtolower($parts['host']) : '';
        $path   = isset($parts['path']) ? $parts['path'] : '';
        $query  = isset($parts['query']) ? $parts['query'] : '';

        // whatsapp://send?phone=...
        if ($scheme === 'whatsapp') {
            parse_str($query, $q);
            if (!empty($q['phone'])) {
                $qp = preg_replace('/\D+/', '', (string)$q['phone']);
                return $qp === $digits;
            }
            return false;
        }

        // api.whatsapp.com/send?…&phone=... ou /message/...
        if ($host === 'api.whatsapp.com') {
            if ($path === '/send' || strpos($path, '/message') === 0) {
                parse_str($query, $q);
                if (!empty($q['phone'])) {
                    $qp = preg_replace('/\D+/', '', (string)$q['phone']);
                    return $qp === $digits;
                }
            }
            return false;
        }

        // wa.me/<phone> ou wa.me/message/...
        if ($host === 'wa.me') {
            // /<phone>
            if (preg_match('#^/(?:\+|%2B)?' . $p . '$#i', $path)) {
                return true;
            }
            // /message/<code>
            if (strpos($path, '/message/') === 0) {
                return true;
            }
            return false;
        }

        return false;
    }

    /**
     * Verificar se telefone é válido
     *
     * @param string $phone Número de telefone
     * @return bool
     */
    public function isValidPhone($phone)
    {
        if ($phone === null || $phone === '') {
            return false;
        }

        // Normalizar e verificar comprimento
        $digits_only = preg_replace('/\D+/', '', (string) $phone);
        return strlen($digits_only) >= 10 && strlen($digits_only) <= 15;
    }
}
