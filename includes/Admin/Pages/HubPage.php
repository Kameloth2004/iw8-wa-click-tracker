<?php
/** ARQUIVO: wp-content/plugins/iw8-wa-click-tracker/includes/Admin/Pages/HubPage.php */
namespace IW8\WaClickTracker\Admin\Pages;

if (!defined('ABSPATH')) {
    exit;
}

class HubPage
{
    // Removido typed properties para compatibilidade com PHP < 7.4
    private $opt_endpoint = 'iw8_hub_endpoint';
    private $opt_host     = 'iw8_hub_host';
    private $opt_token    = 'iw8_hub_token';

    /** Quantidade padrão de eventos para envio manual */
    private $default_batch_size = 50;

    public function render()
    {
        if (!current_user_can('manage_options')) {
            wp_die(__('Você não tem permissão para acessar esta página.', 'iw8-wa-click-tracker'));
        }

        $saved_notice  = null;
        $result_notice = null;

        // Trata POST (salvar configs)
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['_wpnonce']) && wp_verify_nonce($_POST['_wpnonce'], 'iw8_hub_save')) {
            $endpoint = isset($_POST['iw8_hub_endpoint']) ? trim((string)$_POST['iw8_hub_endpoint']) : '';
            $host     = isset($_POST['iw8_hub_host']) ? trim((string)$_POST['iw8_hub_host']) : '';
            $token    = isset($_POST['iw8_hub_token']) ? trim((string)$_POST['iw8_hub_token']) : '';

            // Normalizações simples
            $endpoint = esc_url_raw($endpoint);
            $host = preg_replace('#^https?://#i', '', $host);
            $host = explode('/', $host, 2)[0];
            $host = strtolower($host);

            update_option($this->opt_endpoint, $endpoint);
            update_option($this->opt_host, $host);
            update_option($this->opt_token, $token);
            $saved_notice = __('Configurações salvas.', 'iw8-wa-click-tracker');
        }

        // Trata POST (enviar agora)
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['_wpnonce_send']) && wp_verify_nonce($_POST['_wpnonce_send'], 'iw8_hub_sendnow')) {
            $batch = isset($_POST['iw8_hub_batch']) ? max(1, (int)$_POST['iw8_hub_batch']) : $this->default_batch_size;
            $result_notice = $this->send_now($batch);
        }

        $endpoint = get_option($this->opt_endpoint, 'https://clicktracker.iw8api.com.br/api/ingest.php');
        $host     = get_option($this->opt_host, '');
        $token    = get_option($this->opt_token, '');

        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Hub (Envio automático)', 'iw8-wa-click-tracker'); ?></h1>

            <?php if ($saved_notice): ?>
                <div class="notice notice-success"><p><?php echo esc_html($saved_notice); ?></p></div>
            <?php endif; ?>
            <?php if ($result_notice): ?>
                <div class="notice notice-info"><p><?php echo wp_kses_post($result_notice); ?></p></div>
            <?php endif; ?>

            <p><?php esc_html_e('Configure abaixo a conexão com o IW8 ClickTracker Hub para envio de cliques.', 'iw8-wa-click-tracker'); ?></p>

            <form method="post" style="margin-bottom:24px;">
                <?php wp_nonce_field('iw8_hub_save'); ?>

                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row"><label for="iw8_hub_endpoint"><?php esc_html_e('Endpoint do Hub', 'iw8-wa-click-tracker'); ?></label></th>
                        <td>
                            <input name="iw8_hub_endpoint" id="iw8_hub_endpoint" type="url" class="regular-text"
                                   value="<?php echo esc_attr($endpoint); ?>" placeholder="https://clicktracker.iw8api.com.br/api/ingest.php">
                            <p class="description"><?php esc_html_e('URL do endpoint de ingestão do Hub.', 'iw8-wa-click-tracker'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="iw8_hub_host"><?php esc_html_e('Host (seu domínio)', 'iw8-wa-click-tracker'); ?></label></th>
                        <td>
                            <input name="iw8_hub_host" id="iw8_hub_host" type="text" class="regular-text"
                                   value="<?php echo esc_attr($host); ?>" placeholder="ex.: andaimes-andaimes.com.br">
                            <p class="description"><?php esc_html_e('Exatamente como cadastrado no Hub (sem http/https).', 'iw8-wa-click-tracker'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="iw8_hub_token"><?php esc_html_e('Token', 'iw8-wa-click-tracker'); ?></label></th>
                        <td>
                            <input name="iw8_hub_token" id="iw8_hub_token" type="text" class="regular-text"
                                   value="<?php echo esc_attr($token); ?>" placeholder="SHA-256 (64 hex) ou segredo em texto">
                            <p class="description"><?php esc_html_e('Cole aqui o token do seu domínio (conforme o Hub).', 'iw8-wa-click-tracker'); ?></p>
                        </td>
                    </tr>
                </table>

                <?php submit_button(__('Salvar alterações', 'iw8-wa-click-tracker')); ?>
            </form>

            <hr>

            <h2><?php esc_html_e('Enviar agora (teste manual)', 'iw8-wa-click-tracker'); ?></h2>
            <p class="description"><?php esc_html_e('Envia um lote pequeno de cliques recentes para o Hub para validação ponta-a-ponta.', 'iw8-wa-click-tracker'); ?></p>

            <form method="post" style="margin-top:10px;">
                <?php wp_nonce_field('iw8_hub_sendnow', '_wpnonce_send'); ?>
                <label for="iw8_hub_batch"><?php esc_html_e('Quantidade de eventos', 'iw8-wa-click-tracker'); ?></label>
                <input type="number" min="1" max="500" step="1" name="iw8_hub_batch" id="iw8_hub_batch"
                       value="<?php echo (int)$this->default_batch_size; ?>" style="width:100px; margin-right:10px;">
                <?php submit_button(__('Enviar agora', 'iw8-wa-click-tracker'), 'primary', 'iw8_hub_send_btn', false); ?>
            </form>

            <p><em><?php esc_html_e('Próximo passo, em outra etapa: adicionar cron/agenda para envios automáticos.', 'iw8-wa-click-tracker'); ?></em></p>
        </div>
        <?php
    }

    /**
     * Coleta cliques recentes do plugin e envia para o Hub.
     * @param int $batchSize
     * @return string HTML curto com o resultado
     */
    private function send_now($batchSize)
    {
        global $wpdb;

        $endpoint = trim((string)get_option($this->opt_endpoint, ''));
        $host     = trim((string)get_option($this->opt_host, ''));
        $token    = trim((string)get_option($this->opt_token, ''));

        if ($endpoint === '' || $host === '' || $token === '') {
            return '<strong>Falha:</strong> preencha Endpoint, Host e Token antes de enviar.';
        }

        // Tabelas do plugin
        $t_clicks = $wpdb->prefix . 'iw8_wa_clicks';
        // Verifica existência da tabela
        $has_table = (bool)$wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = %s",
            $t_clicks
        ));
        if (!$has_table) {
            return '<strong>Falha:</strong> tabela de cliques não encontrada: <code>' . esc_html($t_clicks) . '</code>';
        }

        // Coleta cliques mais recentes (últimos 7 dias, limit N)
        $sql = $wpdb->prepare(
            "SELECT id, clicked_at, page_url, element_text
             FROM {$t_clicks}
             WHERE clicked_at >= (NOW() - INTERVAL 7 DAY)
             ORDER BY id DESC
             LIMIT %d",
            (int)$batchSize
        );
        $rows = $wpdb->get_results($sql);
        if (!$rows) {
            return 'Nenhum clique recente para enviar.';
        }

        // Monta eventos
        $events = [];
        $site_home = home_url('/');
        $plugin_version = defined('IW8_WA_CLICKTRACKER_VERSION') ? IW8_WA_CLICKTRACKER_VERSION : '1.4.5';

        foreach ($rows as $r) {
            $event_uid = hash('sha256', $host . '|' . $r->id); // idempotente por site+id
            $clicked_at_iso = '';
            if (!empty($r->clicked_at)) {
                $ts = strtotime($r->clicked_at);
                if ($ts) {
                    $clicked_at_iso = date('c', $ts);
                }
            }

            $events[] = [
                'event_uid'   => $event_uid,
                'clicked_at'  => $clicked_at_iso ?: $r->clicked_at,
                'page_url'    => (string)$r->page_url,
                'referer'     => '',
                'element_text'=> (string)$r->element_text,
                'user_agent'  => 'wp-admin-send-now',
                'geo'         => [ 'ip_hash' => null ],
            ];
        }

        $payload = [
            'schema_version' => '1.4.5',
            'plugin_version' => $plugin_version,
            'site' => [
                'domain' => $host,
                'wp_home'=> $site_home,
            ],
            'token'  => $token,
            'events' => $events,
        ];

        $args = [
            'headers' => [
                'Content-Type' => 'application/json; charset=utf-8',
            ],
            'body'      => wp_json_encode($payload),
            'timeout'   => 15,
            'sslverify' => true,
        ];

        $res = wp_remote_post($endpoint, $args);
        if (is_wp_error($res)) {
            return '<strong>Erro de rede:</strong> ' . esc_html($res->get_error_message());
        }

        $code = wp_remote_retrieve_response_code($res);
        $body = wp_remote_retrieve_body($res);
        $safe = esc_html($body);

        if ($code >= 200 && $code < 300) {
            return '<strong>Envio OK:</strong> ' . $safe;
        }
        return '<strong>Falha (' . (int)$code . '):</strong> <code>' . $safe . '</code>';
    }
}

