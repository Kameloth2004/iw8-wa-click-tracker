<?php
/**
 * Plugin Name: IW8 – Rastreador de Cliques WhatsApp
 * Description: Registra cliques em links do WhatsApp (nº 554832389838) e mostra relatório no WP-Admin. Aceita http/https, api.whatsapp.com e wa.me com quaisquer parâmetros.
 * Version: 1.1.7
 * Author: IW8
 */

if (!defined('ABSPATH')) { exit; }

class IW8_WA_Click_Tracker {
    private $table;
    private $phone = '554832389838'; // DDI+DDD+telefone, sem +
    private $action = 'iw8_wa_click';
    private $cap_view = 'manage_options';

    public function __construct() {
        global $wpdb;
        $this->table = $wpdb->prefix . 'wa_clicks';

        register_activation_hook(__FILE__, [$this, 'activate']);

        // Garante a tabela mesmo se o hook de ativação não rodar
        add_action('admin_init', [$this, 'ensure_table']);

        // Frontend
        add_action('wp_enqueue_scripts', [$this, 'enqueue_script_footer']);

        // AJAX
        add_action('wp_ajax_' . $this->action, [$this, 'handle_click']);
        add_action('wp_ajax_nopriv_' . $this->action, [$this, 'handle_click']);

        // Admin
        add_action('admin_menu', [$this, 'admin_menu']);
        add_action('admin_init', [$this, 'maybe_export_csv']);

        // Diagnóstico
        add_action('admin_post_iw8_wa_insert_test', [$this, 'handle_insert_test']);
    }

    public function activate() {
        $this->create_table();
    }

    public function ensure_table() {
        global $wpdb;
        // Usar SHOW TABLES LIKE para maior compatibilidade (alguns hosts restringem information_schema)
        $exists = $wpdb->get_var(
            $wpdb->prepare('SHOW TABLES LIKE %s', $this->table)
        );
        if ($exists !== $this->table) {
            $this->create_table();
        }
    }

    private function create_table() {
        global $wpdb;
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        $charset_collate = $wpdb->get_charset_collate();
        $sql = "CREATE TABLE {$this->table} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            url VARCHAR(255) NOT NULL,
            page_url TEXT NULL,
            element_tag VARCHAR(50) NULL,
            element_text TEXT NULL,
            user_id BIGINT UNSIGNED NULL,
            user_agent VARCHAR(255) NULL,
            clicked_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            INDEX (clicked_at)
        ) $charset_collate;";
        dbDelta($sql);
    }

    public function enqueue_script_footer() {
        if (is_admin()) return;

        wp_register_script('iw8-wa-click-tracker', false, [], '1.1.7', true);

        $data = [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce'    => wp_create_nonce($this->action . '_nonce'),
            'action'   => $this->action,
            'phone'    => $this->phone,
        ];
        // Define dados e força não usar sendBeacon por padrão (alguns servidores retornam 400 para Beacon)
        // Ativa debug por padrão para identificar problemas
        wp_add_inline_script(
            'iw8-wa-click-tracker',
            'window.iw8WaData = ' . wp_json_encode($data) . '; window.iw8WaNoBeacon = true; window.iw8WaDebug = true;',
            'before'
        );

        // NOWDOC para não quebrar as barras invertidas da regex
        $inline_js = <<<'JS'
(function(){
  function sendClick(payload){
  try{
    var d = window.iw8WaData, u = d.ajax_url;
    payload.action = d.action; payload.nonce = d.nonce;

    // Monta urlencoded uma vez
    var params = new URLSearchParams();
    for (var k in payload) {
      if (Object.prototype.hasOwnProperty.call(payload, k)) {
        params.append(k, String(payload[k] ?? ''));
      }
    }

    // Tenta Beacon com URLENCODED (evita multipart/form-data bloqueado por WAF)
    var ok = false;
    if (!window.iw8WaNoBeacon && navigator && typeof navigator.sendBeacon === 'function') {
      try {
        var blob = new Blob([params.toString()], {type: 'application/x-www-form-urlencoded; charset=UTF-8'});
        ok = navigator.sendBeacon(u, blob);
      } catch(e) { ok = false; }
    }
    if (ok) return;

    // Fallback: fetch urlencoded
    if (window.iw8WaDebug) console.log('[IW8 Track] Enviando via fetch:', u, params.toString());
    fetch(u, {
      method:'POST',
      headers:{'Content-Type':'application/x-www-form-urlencoded; charset=UTF-8'},
      body: params.toString(),
      keepalive:true,
      cache:'no-store',
      credentials:'same-origin'
    }).then(function(response) {
      if (window.iw8WaDebug) console.log('[IW8 Track] Resposta fetch:', response.status, response.ok);
      return response.json();
    }).catch(function(error) {
      if (window.iw8WaDebug) console.log('[IW8 Track] Erro fetch:', error);
    });
  } catch(e){}
}

  var phone = (window.iw8WaData.phone || '').replace(/[^0-9]/g,'');
  // Aceita + ou %2B antes do número e também shortlinks de mensagem (sem phone explícito)
  var re = new RegExp(
    '^https?:\\/\\/(?:'
      + 'api\\.whatsapp\\.com\\/(?:send|message)(?:\\?|\\/)?[^#]*\\bphone=(?:%2B|\\+)?' + phone + '\\b'
      + '|' 
      + 'wa\\.me\\/(?:%2B|\\+)?' + phone + '(?:[?#\\/]|$)'
      + '|' 
      + 'api\\.whatsapp\\.com\\/message\\/[^?#\\/]+'
      + '|' 
      + 'wa\\.me\\/message\\/[^?#\\/]+'
    + ')',
    'i'
  );

  // Cache para evitar envios duplicados da mesma URL
  var sentUrls = {};
  
  function track(url, tag, text){
    if (!url || !re.test(url)) {
      if (window.iw8WaDebug) console.log('[IW8 Track] URL não corresponde:', url, 'Regex:', re.source);
      return;
    }
    
    // Evita envios duplicados da mesma URL em 2 segundos
    var now = Date.now();
    if (sentUrls[url] && (now - sentUrls[url]) < 2000) {
      if (window.iw8WaDebug) console.log('[IW8 Track] URL já enviada recentemente:', url);
      return;
    }
    
    var t = (text || '').trim(); if (t.length>500) t = t.slice(0,500);
    if (window.iw8WaDebug) console.log('[IW8 Track] Enviando clique:', {url:url, tag:tag, text:t});
    
    sentUrls[url] = now;
    sendClick({ url:url, page_url:location.href, element_tag:tag || 'AUTO', element_text:t });
  }

  function maybeFromElement(el){
    if (!el) return;
    // href direto
    var href = el.getAttribute && el.getAttribute('href') || '';
    if (href && re.test(href)) {
      track(href, (el.tagName||'A').toUpperCase(), el.innerText||el.textContent||'');
      return;
    }
    // onclick com URL
    var onclick = (el.getAttribute && el.getAttribute('onclick')) || '';
    if (onclick && /whatsapp|wa\\.me|phone\\s*=/.test(onclick)) {
      var m = onclick.match(/https?:\/\/[^'"\s)]+/i);
      if (m && re.test(m[0])) track(m[0], (el.tagName||'EL').toUpperCase(), el.innerText||el.textContent||'');
      return;
    }
    // data-href/data-url
    var dh = el.getAttribute && (el.getAttribute('data-href') || el.getAttribute('data-url')) || '';
    if (dh && re.test(dh)) {
      track(dh, (el.tagName||'EL').toUpperCase()+'.DATA-HREF', el.innerText||el.textContent||'');
    }
  }

  // Interações do usuário - apenas click para evitar duplicações
  document.addEventListener('click', function(ev){
    var el = ev.target && ev.target.closest ? ev.target.closest('a,button,[role="button"],[onclick],[data-href],[data-url]') : null;
    if (el) maybeFromElement(el);
  }, true);

  // window.open(...)
  var origOpen = window.open;
  window.open = function(url){
    try{ if (typeof url==='string') track(url, 'WINDOW.OPEN', 'open'); }catch(e){}
    return origOpen.apply(this, arguments);
  };

  // location.assign / location.replace
  try{
    var loc = window.location;
    var origAssign = loc.assign ? loc.assign.bind(loc) : null;
    var origReplace = loc.replace ? loc.replace.bind(loc) : null;
    if (origAssign) loc.assign = function(url){ try{ if (typeof url==='string') track(url, 'LOCATION.ASSIGN', 'assign'); }catch(e){} return origAssign(url); };
    if (origReplace) loc.replace = function(url){ try{ if (typeof url==='string') track(url, 'LOCATION.REPLACE', 'replace'); }catch(e){} return origReplace(url); };
  }catch(e){}

  // Programático: a.click()
  try{
    var A = window.HTMLAnchorElement && window.HTMLAnchorElement.prototype;
    if (A && A.click) {
      var origClick = A.click;
      A.click = function(){
        try{
          var href = this.getAttribute('href') || '';
          if (href) track(href, 'A.CLICK()', this.innerText||this.textContent||'');
        }catch(e){}
        return origClick.apply(this, arguments);
      };
    }
  }catch(e){}
})();
JS;

        wp_add_inline_script('iw8-wa-click-tracker', $inline_js);
        wp_enqueue_script('iw8-wa-click-tracker');
    }

    public function handle_click() {
        // Log para debug
        error_log('[IW8 WA Click] Requisição recebida: ' . print_r($_POST, true));
        
        check_ajax_referer($this->action . '_nonce', 'nonce');

        $url         = esc_url_raw( wp_unslash( $_POST['url'] ?? '' ) );
        $page_url    = isset($_POST['page_url']) ? esc_url_raw(wp_unslash($_POST['page_url'])) : '';
        $element_tag = isset($_POST['element_tag']) ? sanitize_text_field(wp_unslash($_POST['element_tag'])) : '';
        $element_text= isset($_POST['element_text']) ? wp_strip_all_tags(wp_unslash($_POST['element_text'])) : '';

        // Regex robusta: aceita + ou %2B antes do número e shortlinks de mensagem
        $p = preg_quote($this->phone, '/');
        $pattern = '/^https?:\/\/(?:'
                 . 'api\.whatsapp\.com\/(?:send|message)(?:\?|\/)?[^#]*\bphone=(?:%2B|\+)?' . $p . '\b'
                 . '|'
                 . 'wa\.me\/(?:%2B|\+)?' . $p . '(?:[?#\/]|$)'
                 . '|'
                 . 'api\.whatsapp\.com\/message\/[^?#\/]+'
                 . '|'
                 . 'wa\.me\/message\/[^?#\/]+'
                 . ')/i';
        if (!preg_match($pattern, $url)) {
            // Evitar erro 400 no console quando a URL não corresponde ao alvo
            error_log('[IW8 WA Click] URL não corresponde ao padrão: ' . $url . ' | Padrão: ' . $pattern);
            wp_send_json_success(['ignored' => true]);
        }

        $user_id = get_current_user_id();
        $ua      = substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 250);
        $now     = current_time('mysql');

        global $wpdb;
        $data = [
            'url'          => $url,
            'page_url'     => $page_url,
            'element_tag'  => $element_tag,
            'element_text' => $element_text,
            'user_id'      => $user_id ?: null,
            'user_agent'   => $ua,
            'clicked_at'   => $now,
        ];
        $formats = ['%s','%s','%s','%s','%d','%s','%s'];

        $inserted = $wpdb->insert($this->table, $data, $formats);

        // Se falhar, tenta criar a tabela e inserir novamente
        if ($inserted === false) {
            $this->create_table();
            $inserted = $wpdb->insert($this->table, $data, $formats);
        }

        if ($inserted === false) {
            error_log('[IW8 WA Click] Falha ao inserir no banco: ' . $wpdb->last_error);
            wp_send_json_error([
                'message' => 'Falha ao registrar clique no banco.',
                'error'   => $wpdb->last_error,
            ], 500);
        }

        error_log('[IW8 WA Click] Clique registrado com sucesso. ID: ' . $wpdb->insert_id);
        wp_send_json_success(['ok' => true]);
    }

    /** -------------------- ADMIN UI -------------------- */

    public function admin_menu() {
        add_menu_page(
            'WA Cliques',
            'WA Cliques',
            $this->cap_view,
            'iw8-wa-clicks',
            [$this, 'render_admin_page'],
            'dashicons-chart-line',
            56
        );

        add_submenu_page(
            'iw8-wa-clicks',
            'Diagnóstico',
            'Diagnóstico',
            $this->cap_view,
            'iw8-wa-clicks-dbg',
            [$this, 'render_diag_page']
        );
    }

    private function summarize_counts($where_sql = '', $where_params = []) {
        global $wpdb;

        $total_sql = "SELECT COUNT(*) FROM {$this->table}";
        if ($where_sql) $total_sql .= " WHERE $where_sql";
        $total = (int) $wpdb->get_var($wpdb->prepare($total_sql, $where_params));

        $d7  = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$this->table} WHERE clicked_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)");
        $d30 = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$this->table} WHERE clicked_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)");

        return [$total, $d7, $d30];
    }

    public function render_admin_page() {
        if (!current_user_can($this->cap_view)) {
            wp_die(__('Você não tem permissão para ver esta página.'));
        }

        global $wpdb;

        $paged     = max(1, (int)($_GET['paged'] ?? 1));
        $per_page  = 20;
        $offset    = ($paged - 1) * $per_page;
        $s         = isset($_GET['s']) ? sanitize_text_field(wp_unslash($_GET['s'])) : '';
        $from      = isset($_GET['from']) ? sanitize_text_field(wp_unslash($_GET['from'])) : '';
        $to        = isset($_GET['to']) ? sanitize_text_field(wp_unslash($_GET['to'])) : '';

        $where = [];
        $params = [];

        // Mostrar apenas registros deste telefone (aceita + ou %2B)
        $where[] = "url REGEXP %s";
        $params[] = 'phone=(' . $this->phone . '|%2B' . $this->phone . '|\\+' . $this->phone . ')|wa\\.me/(' . $this->phone . '|%2B' . $this->phone . '|\\+' . $this->phone . ')';

        if ($s !== '') {
            $where[] = "page_url LIKE %s";
            $params[] = '%' . $wpdb->esc_like($s) . '%';
        }
        if ($from !== '') {
            $where[] = "DATE(clicked_at) >= %s";
            $params[] = $from;
        }
        if ($to !== '') {
            $where[] = "DATE(clicked_at) <= %s";
            $params[] = $to;
        }

        $where_sql = implode(' AND ', $where);

        $count_sql = "SELECT COUNT(*) FROM {$this->table} WHERE $where_sql";
        $total_items = (int) $wpdb->get_var($wpdb->prepare($count_sql, $params));
        $total_pages = max(1, (int) ceil($total_items / $per_page));

        $sql = "SELECT id, clicked_at, page_url, element_tag, element_text, user_id, user_agent, url
                FROM {$this->table}
                WHERE $where_sql
                ORDER BY clicked_at DESC
                LIMIT %d OFFSET %d";
        $query_params = array_merge($params, [$per_page, $offset]);
        $rows = $wpdb->get_results($wpdb->prepare($sql, $query_params));

        [$filtered_total, $last7, $last30] = $this->summarize_counts($where_sql, $params);

        $nonce = wp_create_nonce('iw8_wa_export');

        ?>
        <div class="wrap">
            <h1>WA Cliques</h1>

            <div style="display:flex; gap:16px; margin:12px 0; flex-wrap:wrap;">
                <div style="background:#fff;border:1px solid #ddd;padding:12px 16px;border-radius:8px;min-width:220px;">
                    <strong>Total (filtro atual):</strong><br><?php echo esc_html(number_format_i18n($filtered_total)); ?>
                </div>
                <div style="background:#fff;border:1px solid #ddd;padding:12px 16px;border-radius:8px;min-width:220px;">
                    <strong>Últimos 7 dias:</strong><br><?php echo esc_html(number_format_i18n($last7)); ?>
                </div>
                <div style="background:#fff;border:1px solid #ddd;padding:12px 16px;border-radius:8px;min-width:220px;">
                    <strong>Últimos 30 dias:</strong><br><?php echo esc_html(number_format_i18n($last30)); ?>
                </div>
            </div>

            <form method="get" style="margin:16px 0;">
                <input type="hidden" name="page" value="iw8-wa-clicks">
                <label for="s">Busca (URL da página contém):</label>
                <input type="text" id="s" name="s" value="<?php echo esc_attr($s); ?>" style="min-width:260px;">

                <label for="from" style="margin-left:12px;">De:</label>
                <input type="date" id="from" name="from" value="<?php echo esc_attr($from); ?>">

                <label for="to" style="margin-left:12px;">Até:</label>
                <input type="date" id="to" name="to" value="<?php echo esc_attr($to); ?>">

                <button class="button button-primary" type="submit" style="margin-left:8px;">Filtrar</button>

                <a class="button" href="<?php echo esc_url(admin_url('admin.php?page=iw8-wa-clicks')); ?>" style="margin-left:4px;">Limpar</a>

                <a class="button button-secondary" style="margin-left:8px;"
                   href="<?php echo esc_url(add_query_arg([
                        'page' => 'iw8-wa-clicks',
                        'export' => 'csv',
                        's' => $s,
                        'from' => $from,
                        'to' => $to,
                        '_wpnonce' => $nonce
                    ], admin_url('admin.php'))); ?>">
                    Exportar CSV
                </a>
            </form>

            <table class="widefat fixed striped">
                <thead>
                    <tr>
                        <th style="width:140px;">Data/Hora</th>
                        <th>Página (origem)</th>
                        <th style="width:120px;">Elemento</th>
                        <th>Texto visível</th>
                        <th style="width:90px;">Usuário</th>
                        <th>User-Agent</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($rows)): ?>
                    <tr><td colspan="6">Nenhum registro encontrado.</td></tr>
                <?php else: foreach ($rows as $r): ?>
                    <tr>
                        <td><?php echo esc_html( date_i18n('Y-m-d H:i', strtotime($r->clicked_at)) ); ?></td>
                        <td>
                            <?php if ($r->page_url): ?>
                                <a href="<?php echo esc_url($r->page_url); ?>" target="_blank" rel="noopener noreferrer">
                                    <?php echo esc_html(wp_trim_words($r->page_url, 12)); ?>
                                </a>
                            <?php endif; ?>
                        </td>
                        <td><?php echo esc_html($r->element_tag); ?></td>
                        <td><?php echo esc_html(wp_trim_words($r->element_text ?: '', 20)); ?></td>
                        <td><?php echo $r->user_id ? ('#' . (int)$r->user_id) : '-'; ?></td>
                        <td title="<?php echo esc_attr($r->user_agent); ?>">
                            <?php echo esc_html(wp_trim_words($r->user_agent ?: '', 8)); ?>
                        </td>
                    </tr>
                <?php endforeach; endif; ?>
                </tbody>
            </table>

            <?php if ($total_pages > 1): ?>
                <div class="tablenav">
                    <div class="tablenav-pages">
                        <?php
                        $base_url = remove_query_arg('paged');
                        $links = paginate_links([
                            'base'      => add_query_arg('paged', '%#%', $base_url),
                            'format'    => '',
                            'prev_text' => '&laquo;',
                            'next_text' => '&raquo;',
                            'total'     => $total_pages,
                            'current'   => $paged,
                            'type'      => 'array',
                        ]);
                        if ($links) {
                            echo '<span class="pagination-links">' . join(' ', $links) . '</span>';
                        }
                        ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
        <?php
    }

    public function render_diag_page() {
        if (!current_user_can($this->cap_view)) wp_die('Sem permissão.');
        global $wpdb;

        $count = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$this->table}");
        $rows  = $wpdb->get_results("SELECT id, clicked_at, url, page_url FROM {$this->table} ORDER BY id DESC LIMIT 20");
        $nonce = wp_create_nonce('iw8_wa_insert_test');

        echo '<div class="wrap"><h1>Diagnóstico – WA Cliques</h1>';
        echo '<p><strong>Total de registros:</strong> ' . esc_html(number_format_i18n($count)) . '</p>';

        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" style="margin:12px 0;">';
        echo '<input type="hidden" name="action" value="iw8_wa_insert_test">';
        echo '<input type="hidden" name="_wpnonce" value="' . esc_attr($nonce) . '">';
        echo '<button class="button button-primary" type="submit">Inserir registro de teste</button> ';
        echo '<span style="opacity:.75;">(insere 1 linha padrão para validar escrita no banco)</span>';
        echo '</form>';

        if (empty($rows)) {
            echo '<p>Nenhum registro encontrado.</p>';
        } else {
            echo '<table class="widefat fixed striped"><thead><tr>
                    <th style="width:80px;">ID</th><th style="width:160px;">Data/Hora</th><th>URL</th><th style="width:120px;">Página</th>
                  </tr></thead><tbody>';
            foreach ($rows as $r) {
                echo '<tr>';
                echo '<td>' . (int)$r->id . '</td>';
                echo '<td>' . esc_html(date_i18n('Y-m-d H:i:s', strtotime($r->clicked_at))) . '</td>';
                echo '<td style="word-break:break-all;">' . esc_html($r->url) . '</td>';
                echo '<td style="word-break:break-all;">' . ($r->page_url ? '<a href="' . esc_url($r->page_url) . '" target="_blank" rel="noopener">abrir</a>' : '-') . '</td>';
                echo '</tr>';
            }
            echo '</tbody></table>';
        }

        echo '</div>';
    }

    public function handle_insert_test() {
        if (!current_user_can($this->cap_view)) wp_die('Sem permissão.');
        check_admin_referer('iw8_wa_insert_test');

        global $wpdb;
        $wpdb->insert($this->table, [
            'url'          => 'https://api.whatsapp.com/send?1=pt_BR&phone=' . $this->phone . '&text=teste',
            'page_url'     => home_url('/'),
            'element_tag'  => 'ADMIN',
            'element_text' => 'Inserção de teste',
            'user_id'      => get_current_user_id() ?: null,
            'user_agent'   => 'WP-Admin/Diag',
            'clicked_at'   => current_time('mysql'),
        ], ['%s','%s','%s','%s','%d','%s','%s']);

        wp_safe_redirect( admin_url('admin.php?page=iw8-wa-clicks-dbg') );
        exit;
    }

    public function maybe_export_csv() {
        if (!is_admin()) return;
        if (!isset($_GET['page']) || $_GET['page'] !== 'iw8-wa-clicks') return;
        if (!isset($_GET['export']) || $_GET['export'] !== 'csv') return;

        if (!current_user_can($this->cap_view)) {
            wp_die(__('Sem permissão.'));
        }
        check_admin_referer('iw8_wa_export');

        global $wpdb;

        $s    = isset($_GET['s']) ? sanitize_text_field(wp_unslash($_GET['s'])) : '';
        $from = isset($_GET['from']) ? sanitize_text_field(wp_unslash($_GET['from'])) : '';
        $to   = isset($_GET['to']) ? sanitize_text_field(wp_unslash($_GET['to'])) : '';

        $where = ["url REGEXP %s"];
        $params = ['phone=(' . $this->phone . '|%2B' . $this->phone . '|\\+' . $this->phone . ')|wa\\.me/(' . $this->phone . '|%2B' . $this->phone . '|\\+' . $this->phone . ')'];

        if ($s !== '') {
            $where[] = "page_url LIKE %s";
            $params[] = '%' . $wpdb->esc_like($s) . '%';
        }
        if ($from !== '') {
            $where[] = "DATE(clicked_at) >= %s";
            $params[] = $from;
        }
        if ($to !== '') {
            $where[] = "DATE(clicked_at) <= %s";
            $params[] = $to;
        }

        $where_sql = implode(' AND ', $where);
        $sql = "SELECT id, clicked_at, url, page_url, element_tag, element_text, user_id, user_agent
                FROM {$this->table}
                WHERE $where_sql
                ORDER BY clicked_at DESC";
        $rows = $wpdb->get_results($wpdb->prepare($sql, $params), ARRAY_A);

        nocache_headers();
        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="wa-cliques-' . date('Ymd-His') . '.csv"');

        $out = fopen('php://output', 'w');
        fprintf($out, chr(0xEF).chr(0xBB).chr(0xBF)); // BOM para Excel
        fputcsv($out, ['id','clicked_at','url','page_url','element_tag','element_text','user_id','user_agent']);

        foreach ($rows as $r) {
            fputcsv($out, [
                $r['id'],
                $r['clicked_at'],
                $r['url'],
                $r['page_url'],
                $r['element_tag'],
                $r['element_text'],
                $r['user_id'],
                $r['user_agent'],
            ]);
        }
        fclose($out);
        exit;
    }
} // <-- fecha a classe certinho

// Instancia o plugin
new IW8_WA_Click_Tracker();


