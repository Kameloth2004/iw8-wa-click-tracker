<?php

/**
 * Classe para controlar requisições AJAX relacionadas a cliques
 *
 * @package IW8_WaClickTracker\Ajax
 * @version 1.3.0
 */

namespace IW8\WaClickTracker\Ajax;

// IMPORTS: ajudam o analisador (VSCode/Intelephense) a resolver funções globais do WP/PHP.
use function add_action;
use function check_ajax_referer;
use function current_time;
use function error_log;
use function esc_url_raw;
use function get_current_user_id;
use function get_option;
use function home_url;
use function is_wp_error;
use function print_r;
use function sanitize_text_field;
use function strlen;
use function substr;
use function wp_json_encode;
use function wp_send_json_error;
use function wp_send_json_success;
use function wp_strip_all_tags;

use IW8\WaClickTracker\Database\ClickRepository;
use IW8\WaClickTracker\Frontend\UrlMatcher;

// Prevenir acesso direto
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Classe ClickController
 */
class ClickController
{
    /**
     * @var ClickRepository
     */
    private $repository;

    /**
     * @var UrlMatcher
     */
    private $url_matcher;

    public function __construct()
    {
        $this->repository  = new ClickRepository();
        $this->url_matcher = new UrlMatcher();

        // OBS: se os hooks não forem registrados em outro lugar (Loader), descomente:
        // add_action('wp_ajax_iw8_wa_click',       [$this, 'handle_click']);
        // add_action('wp_ajax_nopriv_iw8_wa_click',[$this, 'handle_click']);
    }

    /**
     * Manipular clique recebido via AJAX
     */
    public function handle_click(): void
    {
        $debug = (bool) get_option('iw8_wa_debug', false);

        /** @var array<string,mixed> $post */
        $post = isset($_POST) && is_array($_POST) ? wp_unslash($_POST) : array();

        if ($debug && function_exists('error_log')) {
            error_log('IW8_WA_CLICK_TRACKER DEBUG: Requisição AJAX recebida');
            error_log('IW8_WA_CLICK_TRACKER DEBUG: POST data: ' . print_r($post, true));
        }

        // === Nonce ===
        $nonce_ok =
            check_ajax_referer('iw8_wa_click', '_ajax_nonce', false) ||
            check_ajax_referer('iw8_wa_click', 'nonce', false) ||
            check_ajax_referer('iw8_wa_click_nonce', 'nonce', false); // compat legado

        if (!$nonce_ok) {
            if ($debug && function_exists('error_log')) {
                error_log('IW8_WA_CLICK_TRACKER DEBUG: Nonce inválido');
            }
            wp_send_json_error(array('ok' => false, 'error' => 'bad_nonce'), 403);
            return;
        }

        if ($debug && function_exists('error_log')) {
            error_log('IW8_WA_CLICK_TRACKER DEBUG: Nonce válido, processando dados');
        }

        // === Payload (campos individuais OU data JSON) ===
        $payload = array();

        if (!empty($post['data'])) {
            $raw = is_string($post['data']) ? $post['data'] : '';
            $arr = json_decode($raw, true);
            if (is_array($arr)) {
                $payload = $arr;
            }
        }

        // Campos individuais têm precedência (mantém compatibilidade)
        $payload['url']          = isset($post['url']) ? $post['url'] : ($payload['url'] ?? '');
        $payload['page_url']     = isset($post['page_url']) ? $post['page_url'] : ($payload['page_url'] ?? '');
        $payload['element_tag']  = isset($post['element_tag']) ? $post['element_tag'] : ($payload['element_tag'] ?? '');
        $payload['element_text'] = isset($post['element_text']) ? $post['element_text'] : ($payload['element_text'] ?? '');
        $payload['user_id']      = isset($post['user_id']) ? $post['user_id'] : ($payload['user_id'] ?? 0);

        // ⬇️ NOVO: aceitar geo opcional do POST (se você decidir enviar do front)
        $payload['country']      = isset($post['country']) ? $post['country'] : ($payload['country'] ?? '');
        $payload['region']       = isset($post['region'])  ? $post['region']  : ($payload['region'] ?? '');
        $payload['city']         = isset($post['city'])    ? $post['city']    : ($payload['city'] ?? '');

        // === Sanitização ===
        $url          = esc_url_raw((string) ($payload['url'] ?? ''));
        $page_url     = esc_url_raw((string) ($payload['page_url'] ?? ''));
        $element_tag  = sanitize_text_field((string) ($payload['element_tag'] ?? ''));
        $element_text = wp_strip_all_tags((string) ($payload['element_text'] ?? ''));
        $user_id      = (int) ($payload['user_id'] ?? 0);

        // ⬇️ NOVO: sanitizar geo do POST (se veio)
        $geo_from_post = array(
            'country' => $this->sanitize_geo((string) ($payload['country'] ?? '')),
            'region'  => $this->sanitize_geo((string) ($payload['region']  ?? '')),
            'city'    => $this->sanitize_geo((string) ($payload['city']    ?? '')),
            'source'  => 'post',
        );

        if ($url === '') {
            wp_send_json_error(array('ok' => false, 'error' => 'missing_url'), 400);
            return;
        }

        // === Telefone configurado ===
        $phone = (string) get_option('iw8_wa_phone', '');
        if ($phone === '' || !$this->url_matcher->isValidPhone($phone)) {
            if ($debug && function_exists('error_log')) {
                error_log('IW8_WA_CLICK_TRACKER DEBUG: Telefone não configurado ou inválido: ' . $phone);
            }
            wp_send_json_success(array('ok' => false, 'ignored' => true, 'reason' => 'no_phone'));
            return;
        }
        if ($debug && function_exists('error_log')) {
            error_log('IW8_WA_CLICK_TRACKER DEBUG: Telefone válido: ' . $phone);
        }

        // === Validar URL alvo ===
        $match_ok = $this->url_matcher->matchesTarget($url, $phone);
        if ($debug && function_exists('error_log')) {
            error_log('IW8_WA_CLICK_TRACKER DEBUG: UrlMatcher resultado=' . ($match_ok ? 'OK' : 'IGNORADA') . ' | URL=' . $url);
        }
        if (!$match_ok) {
            wp_send_json_success(array('ok' => false, 'ignored' => true, 'reason' => 'url_mismatch', 'url' => $url));
            return;
        }

        // === Dados para persistência ===
        $data = array(
            'url'          => $url,
            'page_url'     => $page_url ?: (wp_get_referer() ?: home_url('/')),
            'element_tag'  => $element_tag !== '' ? strtoupper($element_tag) : 'A',
            'element_text' => $element_text,
            'user_agent'   => $this->get_user_agent(),
            'user_id'      => $user_id > 0 ? $user_id : (get_current_user_id() ?: 0),
            'clicked_at'   => current_time('mysql'),
        );

        // ⬇️ NOVO: derivar geo de headers de proxy/CDN (sem IP, sem PII). Fallback: geo do POST.
        $geo = $this->resolve_geo_from_headers();
        if (empty($geo['country']) && empty($geo['region']) && empty($geo['city'])) {
            $geo = $geo_from_post; // só usa se você optar por enviar do front
        }

        if ($debug && function_exists('error_log')) {
            error_log('IW8_WA_CLICK_TRACKER DEBUG: Data pronto p/ insert: ' . wp_json_encode($data));
            error_log('IW8_WA_CLICK_TRACKER DEBUG: GEO derivado: ' . wp_json_encode($geo));
        }

        // === Inserção ===
        global $wpdb;
        /** @var \wpdb $wpdb */
        $result = $this->repository->insertClick($data);

        if (is_wp_error($result)) {
            if ($debug && function_exists('error_log')) {
                error_log('IW8 WaClickTracker Insert Error: ' . $result->get_error_message() . ' | wpdb: ' . $wpdb->last_error);
            }
            wp_send_json_error(array(
                'ok'      => false,
                'error'   => 'db_insert_failed',
                'message' => $result->get_error_message(),
                'wpdb'    => $wpdb->last_error,
            ), 500);
            return;
        }

        if ($debug && function_exists('error_log')) {
            error_log('IW8_WA_CLICK_TRACKER DEBUG: Clique inserido. ID=' . (int) $result);
        }
        
        // Salva cidade / estado / país (metadados)
if (function_exists('iw8_wa_add_click_meta')) {
    iw8_wa_add_click_meta((int)$result, 'country', $geo['country'] ?? '');
    iw8_wa_add_click_meta((int)$result, 'region',  $geo['region']  ?? '');
    iw8_wa_add_click_meta((int)$result, 'city',    $geo['city']    ?? '');
}

        // ⬇️ NOVO: retornar geo NO RESPONSE (para teste/validação). Não persiste ainda.
        wp_send_json_success(array(
            'ok'   => true,
            'id'   => (int) $result,
            'geo'  => array(
                'country' => $geo['country'] ?? '',
                'region'  => $geo['region']  ?? '',
                'city'    => $geo['city']    ?? '',
                'source'  => $geo['source']  ?? '',
            ),
        ));
    }


    /**
     * User-Agent limitado a 255 chars (compatível com schema)
     */
    private function get_user_agent(): ?string
    {
        if (!isset($_SERVER['HTTP_USER_AGENT'])) {
            return null;
        }
        $ua = (string) $_SERVER['HTTP_USER_AGENT'];
        if (strlen($ua) > 255) {
            $ua = substr($ua, 0, 252) . '...';
        }
        return $ua;
    }

    /**
     * ⬇️ NOVO: Sanitização simples para campos de geo (sem números/IPs, remove excesso).
     */
    private function sanitize_geo(string $s): string
    {
        $s = wp_strip_all_tags($s);
        // mantém letras, espaço, hífen, apóstrofo e acentos comuns
        $s = preg_replace('~[^ \-\p{L}\']+~u', '', $s) ?? '';
        return trim($s);
    }

    /**
     * ⬇️ NOVO: Resolve geo por headers comuns de proxy/CDN (sem armazenar IP).
     * Prioridade de fontes:
     *  - Cloudflare: HTTP_CF_IPCOUNTRY (país)
     *  - Cabeçalhos custom: X-Geo-Country / X-Geo-Region / X-Geo-City
     *  - Nginx/Proxy custom: X-App-Country / X-App-Region / X-App-City
     *  - (Opcional futuramente) outros provedores
     */
    private function resolve_geo_from_headers(): array
    {
        $country = '';
        $region  = '';
        $city    = '';
        $source  = '';

        // Cloudflare country (ISO-2)
        if (!empty($_SERVER['HTTP_CF_IPCOUNTRY'])) {
            $country = $this->sanitize_geo((string) $_SERVER['HTTP_CF_IPCOUNTRY']);
            $source  = 'cf-header';
        }

        // Cabeçalhos customizados do seu proxy/CDN (se configurados)
        $hCountry = $_SERVER['HTTP_X_GEO_COUNTRY'] ?? $_SERVER['HTTP_X_APP_COUNTRY'] ?? null;
        $hRegion  = $_SERVER['HTTP_X_GEO_REGION']  ?? $_SERVER['HTTP_X_APP_REGION']  ?? null;
        $hCity    = $_SERVER['HTTP_X_GEO_CITY']    ?? $_SERVER['HTTP_X_APP_CITY']    ?? null;

        if ($hCountry || $hRegion || $hCity) {
            $country = $this->sanitize_geo((string) ($hCountry ?? $country));
            $region  = $this->sanitize_geo((string) ($hRegion  ?? ''));
            $city    = $this->sanitize_geo((string) ($hCity    ?? ''));
            $source  = $source ?: 'proxy-headers';
        }

        return array(
            'country' => $country,
            'region'  => $region,
            'city'    => $city,
            'source'  => $source,
        );
    }
}

