<?php

/**
 * Repositório de cliques
 *
 * @package IW8_WaClickTracker\Database
 * @version 1.3.0
 */

namespace IW8\WaClickTracker\Database;

// Prevenir acesso direto
if (!defined('ABSPATH')) {
    exit;
}

class ClickRepository
{
    /** @var string */
    private $table;

    public function __construct()
    {
        global $wpdb;
        $this->table = $wpdb->prefix . 'wa_clicks';
    }

    /** Nome da tabela (com prefixo) */
    public function getTableName(): string
    {
        return $this->table;
    }

    /**
     * Inserir um clique
     * @param array $data
     * @return int|\WP_Error ID inserido ou WP_Error
     */
    public function insertClick(array $data)
    {
        global $wpdb;

        // Normalizações (evitam erro de tamanho/tipo)
        $url          = isset($data['url']) ? (string)$data['url'] : '';
        $page_url     = isset($data['page_url']) ? (string)$data['page_url'] : null;
        $element_tag  = isset($data['element_tag']) ? (string)$data['element_tag'] : '';
        $element_text = isset($data['element_text']) ? (string)$data['element_text'] : null;
        $user_id      = isset($data['user_id']) && $data['user_id'] !== null ? (int)$data['user_id'] : null;
        $user_agent   = isset($data['user_agent']) ? (string)$data['user_agent'] : null;
        $clicked_at   = !empty($data['clicked_at']) ? (string)$data['clicked_at'] : current_time('mysql');

        // Cortes para colunas varchar
        if (function_exists('mb_substr')) {
            $url         = mb_substr($url, 0, 255);
            $element_tag = mb_substr($element_tag, 0, 50);
            if ($user_agent !== null) {
                $user_agent = mb_substr($user_agent, 0, 255);
            }
        } else {
            $url         = substr($url, 0, 255);
            $element_tag = substr($element_tag, 0, 50);
            if ($user_agent !== null) {
                $user_agent = substr($user_agent, 0, 255);
            }
        }

        // Monta linha
        $row = [
            'url'          => $url,
            'page_url'     => $page_url,     // TEXT, pode ser null
            'element_tag'  => $element_tag,  // varchar(50), NOT NULL na prática aceitamos vazio
            'element_text' => $element_text, // TEXT, pode ser null
            'user_id'      => $user_id,      // bigint unsigned, pode ser null
            'user_agent'   => $user_agent,   // varchar(255), pode ser null
            'clicked_at'   => $clicked_at,   // datetime NOT NULL
        ];

        // Formatos (quando valor é null, o WP/wpdb insere NULL — ok)
        $formats = [
            '%s', // url
            '%s', // page_url
            '%s', // element_tag
            '%s', // element_text
            '%d', // user_id
            '%s', // user_agent
            '%s', // clicked_at
        ];

        // LOG: tabela e dados (para debug)
        if (function_exists('error_log')) {
            error_log('[IW8_WA_CLICK_TRACKER] REPO insert na tabela: ' . $this->table);
            error_log('[IW8_WA_CLICK_TRACKER] REPO dados: ' . print_r($row, true));
        }

        $ok = $wpdb->insert($this->table, $row, $formats);

        if ($ok === false) {
            // LOG de erro SQL
            if (function_exists('error_log')) {
                error_log('[IW8_WA_CLICK_TRACKER] REPO ERRO insert: ' . $wpdb->last_error);
                // Atenção: $wpdb->last_query pode conter dados sensíveis; use só em debug
                error_log('[IW8_WA_CLICK_TRACKER] REPO last_query: ' . $wpdb->last_query);
            }
            return new \WP_Error('db_insert_failed', 'Falha ao inserir clique: ' . $wpdb->last_error);
        }

        $id = (int)$wpdb->insert_id;

        if (function_exists('error_log')) {
            error_log('[IW8_WA_CLICK_TRACKER] REPO inserido com sucesso. ID=' . $id);
        }

        return $id;
    }

    /**
     * Listar cliques
     * @param array $filters  (não usado aqui, mas mantido p/ compat)
     * @param array $opts     ['per_page'=>int, 'offset'=>int]
     * @return array<object>
     */
    public function list(array $filters = [], array $opts = [])
    {
        global $wpdb;

        $per_page = isset($opts['per_page']) ? max(1, (int)$opts['per_page']) : 20;
        $offset   = isset($opts['offset']) ? max(0, (int)$opts['offset']) : 0;

        // ORDER por data (desc)
        $sql = $wpdb->prepare(
            "SELECT id, url, page_url, element_tag, element_text, user_id, user_agent, clicked_at
             FROM {$this->table}
             ORDER BY clicked_at DESC, id DESC
             LIMIT %d OFFSET %d",
            $per_page,
            $offset
        );

        return $wpdb->get_results($sql);
    }

    /**
     * Contagem total
     * @return array{total:int}
     */
    public function countTotals($filters = [])
    {
        global $wpdb;

        // Descobre o nome da tabela (compatível com $this->table ou $this->table_name)
        $table = null;
        if (property_exists($this, 'table_name') && !empty($this->table_name)) {
            $table = $this->table_name;
        } elseif (property_exists($this, 'table') && !empty($this->table)) {
            $table = $this->table;
        } else {
            $table = $wpdb->prefix . 'wa_clicks';
        }

        // --- WHERE base (sem datas) para total/7/30 (mantém s e url_regexp)
        $where  = [];
        $params = [];

        // Busca em page_url OU element_text
        if (!empty($filters['s'])) {
            $like = '%' . $wpdb->esc_like($filters['s']) . '%';
            $where[]  = '(page_url LIKE %s OR element_text LIKE %s)';
            $params[] = $like;
            $params[] = $like;
        }

        // Regex por URL (gerada a partir do telefone)
        if (!empty($filters['url_regexp'])) {
            $where[]  = 'url REGEXP %s';
            $params[] = $filters['url_regexp'];
        }

        // ---------- TOTAL (respeita from/to quando informados) ----------
        $whereTotal  = $where;
        $paramsTotal = $params;

        if (!empty($filters['from'])) {
            $whereTotal[]  = 'clicked_at >= %s';
            $paramsTotal[] = $filters['from'] . ' 00:00:00';
        }
        if (!empty($filters['to'])) {
            $whereTotal[]  = 'clicked_at <= %s';
            $paramsTotal[] = $filters['to'] . ' 23:59:59';
        }

        $sqlTotal = "SELECT COUNT(*) FROM {$table} WHERE 1=1"
            . ($whereTotal ? ' AND ' . implode(' AND ', $whereTotal) : '');
        if ($paramsTotal) {
            $sqlTotal = $wpdb->prepare($sqlTotal, $paramsTotal);
        }
        $total = (int) $wpdb->get_var($sqlTotal);

        // ---------- LAST 7 / LAST 30 (janelas corridas; ignoram from/to) ----------
        $nowTs = current_time('timestamp'); // timezone do WP
        $dt7   = date('Y-m-d H:i:s', $nowTs - 7  * DAY_IN_SECONDS);
        $dt30  = date('Y-m-d H:i:s', $nowTs - 30 * DAY_IN_SECONDS);

        // last 7 dias (mantém s e url_regexp)
        $where7  = array_merge($where, ['clicked_at >= %s']);
        $params7 = array_merge($params, [$dt7]);
        $sql7 = "SELECT COUNT(*) FROM {$table} WHERE 1=1 AND " . implode(' AND ', $where7);
        $sql7 = $wpdb->prepare($sql7, $params7);
        $last7 = (int) $wpdb->get_var($sql7);

        // last 30 dias (mantém s e url_regexp)
        $where30  = array_merge($where, ['clicked_at >= %s']);
        $params30 = array_merge($params, [$dt30]);
        $sql30 = "SELECT COUNT(*) FROM {$table} WHERE 1=1 AND " . implode(' AND ', $where30);
        $sql30 = $wpdb->prepare($sql30, $params30);
        $last30 = (int) $wpdb->get_var($sql30);

        return [
            'total'  => $total,
            'last7'  => $last7,
            'last30' => $last30,
        ];
    }
}
