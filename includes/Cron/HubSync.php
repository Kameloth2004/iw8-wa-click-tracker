<?php
// includes/Cron/HubSync.php
namespace IW8\WaClickTracker\Cron;

if (!defined('ABSPATH')) { exit; }

class HubSync {
    public static function send_batch($batchSize = 100) {
        if (!function_exists('get_option')) { return; }
        $endpoint = trim((string)get_option('iw8_hub_endpoint', ''));
        $host     = trim((string)get_option('iw8_hub_host', ''));
        $token    = trim((string)get_option('iw8_hub_token', ''));

        if ($endpoint === '' || $host === '' || $token === '') {
            return; // ainda não configurado
        }

        global $wpdb;
        $t_clicks = $wpdb->prefix . 'iw8_wa_clicks';
        $has_table = (bool)$wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = %s",
            $t_clicks
        ));
        if (!$has_table) { return; }

        $sql = $wpdb->prepare(
            "SELECT id, clicked_at, page_url, element_text
             FROM {$t_clicks}
             WHERE clicked_at >= (NOW() - INTERVAL 7 DAY)
             ORDER BY id DESC
             LIMIT %d",
            (int)$batchSize
        );
        $rows = $wpdb->get_results($sql);
        if (!$rows) { return; }

        $events = [];
        $site_home = home_url('/');
        $plugin_version = defined('IW8_WA_CLICKTRACKER_VERSION') ? IW8_WA_CLICKTRACKER_VERSION : '1.4.5';

        foreach ($rows as $r) {
            $event_uid = hash('sha256', $host . '|' . $r->id);
            $ts = $r->clicked_at ? strtotime($r->clicked_at) : false;
            $clicked_at_iso = $ts ? date('c', $ts) : (string)$r->clicked_at;

            $events[] = [
                'event_uid'    => $event_uid,
                'clicked_at'   => $clicked_at_iso,
                'page_url'     => (string)$r->page_url,
                'referer'      => '',
                'element_text' => (string)$r->element_text,
                'user_agent'   => 'wp-cron',
                'geo'          => [ 'ip_hash' => null ],
            ];
        }

        $payload = [
            'schema_version' => '1.4.5',
            'plugin_version' => $plugin_version,
            'site' => ['domain' => $host, 'wp_home' => $site_home],
            'token'  => $token,
            'events' => $events,
        ];

        $args = [
            'headers' => ['Content-Type' => 'application/json; charset=utf-8'],
            'body'      => wp_json_encode($payload),
            'timeout'   => 15,
            'sslverify' => true,
        ];
        // Ignora falha silenciosamente (logaremos em etapa futura)
        wp_remote_post($endpoint, $args);
    }
}
