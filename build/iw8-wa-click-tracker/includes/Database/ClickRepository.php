<?php

namespace IW8\WaClickTracker\Database;

if (!defined('ABSPATH')) {
    exit;
}

class ClickRepository
{
    /** Resolve a tabela preferida (nova) com fallback legado. */
    private function resolveTableName()
    {
        global $wpdb;
        $new = $wpdb->prefix . 'iw8_wa_clicks';
        $old = $wpdb->prefix . 'wa_clicks';
        if ($this->tableExists($new)) return $new;
        if ($this->tableExists($old)) return $old;
        return $new;
    }

    private function tableExists($table)
    {
        global $wpdb;
        $like = $wpdb->esc_like($table);
        $sql  = "SHOW TABLES LIKE %s";
        $found = $wpdb->get_var($wpdb->prepare($sql, $like));
        return is_string($found);
    }

    private function discoverSchema($table)
    {
        global $wpdb;
        $cols = array();
        $rows = $wpdb->get_results("SHOW COLUMNS FROM `{$table}`", ARRAY_A);
        if (is_array($rows)) {
            foreach ($rows as $r) {
                if (isset($r['Field'])) {
                    $cols[] = (string)$r['Field'];
                }
            }
        }
        $clickedCol = in_array('clicked_at', $cols, true) ? 'clicked_at'
            : (in_array('created_at', $cols, true) ? 'created_at' : null);
        return array('columns' => $cols, 'clickedCol' => $clickedCol);
    }

    private function selectFields()
    {
        return array('id', 'clicked_at', 'url', 'page_url', 'element_tag', 'element_text', 'user_agent', 'user_id');
    }

    private function normalizeFilters($filters, $schema)
    {
        $f = array(
            'page_url'    => '',
            'element_tag' => '',
            'user_id'     => 0,
            'since'       => '', // ISO 8601 ou Y-m-d H:i:s
            'until'       => '',
            'search'      => '',
        );
        foreach ($f as $k => $v) {
            if (isset($filters[$k])) {
                $f[$k] = $filters[$k];
            }
        }
        // Normalizar datas (aceita ISO)
        foreach (['since', 'until'] as $dk) {
            if (!empty($f[$dk])) {
                $ts = strtotime((string)$f[$dk]);
                if ($ts !== false) {
                    $f[$dk] = gmdate('Y-m-d H:i:s', $ts);
                } else {
                    $f[$dk] = '';
                }
            }
        }
        return $f;
    }

    /** Inserção usada pelo AJAX/REST */
    public function insertClick($data)
    {
        global $wpdb;
        $table = $this->resolveTableName();
        if (!$this->tableExists($table)) {
            return new \WP_Error('table_missing', 'Tabela de cliques não existe.');
        }
        $schema = $this->discoverSchema($table);
        $cols = $schema['columns'];

        $row = array(
            'clicked_at'   => gmdate('Y-m-d H:i:s'),
            'url'          => isset($data['url']) ? (string)$data['url'] : '',
            'page_url'     => isset($data['page_url']) ? (string)$data['page_url'] : '',
            'element_tag'  => isset($data['element_tag']) ? (string)$data['element_tag'] : '',
            'element_text' => isset($data['element_text']) ? (string)$data['element_text'] : '',
            'user_agent'   => isset($data['user_agent']) ? (string)$data['user_agent'] : '',
            'user_id'      => isset($data['user_id']) ? intval($data['user_id']) : 0,
        );

        // Remover colunas que não existem
        foreach (array_keys($row) as $k) {
            if (!in_array($k, $cols, true)) {
                unset($row[$k]);
            }
        }

        // Formats por tipo
        $formats = array();
        foreach ($row as $k => $_) {
            $formats[] = ($k === 'user_id') ? '%d' : '%s';
        }

        $ok = $wpdb->insert($table, $row, $formats);
        if ($ok === false) {
            return new \WP_Error('db_insert_failed', $wpdb->last_error ?: 'Falha ao inserir');
        }
        return (int)$wpdb->insert_id;
    }

    /** Listagem (Relatórios) */
    public function list(array $filters = [], array $pagination = []): array
    {
        global $wpdb;

        $table = $this->resolveTableName();
        if (!$this->tableExists($table)) return [];

        $schema     = $this->discoverSchema($table);
        $cols       = $schema['columns'];
        $clickedCol = $schema['clickedCol'];        // 'clicked_at' | 'created_at' | null
        if (!$clickedCol) return [];                // sem coluna temporal não ordena/pagina

        $f = $this->normalizeFilters($filters, $schema);

        // WHERE + params (helper pra reduzir ifs soltos)
        $where  = [];
        $params = [];
        $add = function (string $cond, $val) use (&$where, &$params) {
            $where[]  = $cond;
            $params[] = $val;
        };

        if (!empty($f['page_url'])     && in_array('page_url', $cols, true))     $add("`page_url` LIKE %s", trailingslashit($f['page_url']) . '%');
        if (!empty($f['element_tag'])  && in_array('element_tag', $cols, true))  $add("`element_tag` = %s", $f['element_tag']);
        if (!empty($f['user_id'])      && in_array('user_id', $cols, true))      $add("`user_id` = %d", (int) $f['user_id']);
        if (!empty($f['since']))                                               $add("`{$clickedCol}` >= %s", $f['since']);
        if (!empty($f['until']))                                               $add("`{$clickedCol}` <= %s", $f['until']);
        if (!empty($f['search'])      && in_array('element_text', $cols, true))  $add("`element_text` LIKE %s", '%' . $wpdb->esc_like($f['search']) . '%');

        $whereSql = $where ? ' WHERE ' . implode(' AND ', $where) : '';

        // SELECT público com alias quando precisar
        $public      = $this->selectFields(); // ['id','clicked_at','url','page_url',...]
        $selectParts = [];
        foreach ($public as $c) {
            if ($c === 'clicked_at') {
                if ($clickedCol === 'clicked_at' && in_array('clicked_at', $cols, true)) {
                    $selectParts[] = '`clicked_at`';
                } elseif ($clickedCol === 'created_at' && in_array('created_at', $cols, true)) {
                    $selectParts[] = '`created_at` AS `clicked_at`';
                }
                continue;
            }
            if (in_array($c, $cols, true)) {
                $selectParts[] = "`{$c}`";
            }
        }
        if (empty($selectParts)) {
            $selectParts[] = in_array('id', $cols, true) ? '`id`' : '1 AS id';
        }
        $selectSql = implode(', ', $selectParts);

        // paginação + ordenação
        $limit  = isset($pagination['limit'])  ? max(1, (int) $pagination['limit'])  : 50;
        $offset = isset($pagination['offset']) ? max(0, (int) $pagination['offset']) : 0;
        $dir    = strtoupper($f['order'] ?? 'DESC');
        $dir    = ($dir === 'ASC') ? 'ASC' : 'DESC';

        $sql = "SELECT {$selectSql}
              FROM `{$table}`{$whereSql}
          ORDER BY `{$clickedCol}` {$dir}, `id` {$dir}
             LIMIT %d OFFSET %d";

        $params[] = $limit;
        $params[] = $offset;

        $rows = $wpdb->get_results($wpdb->prepare($sql, $params), ARRAY_A);
        return is_array($rows) ? $rows : [];
    }


    /** Totais (total/7/30) */
    public function countTotals($filters = array())
    {
        global $wpdb;
        $out = array('total' => 0, 'last7' => 0, 'last30' => 0);
        $table = $this->resolveTableName();
        if (!$this->tableExists($table)) {
            return (object)$out;
        }

        $schema = $this->discoverSchema($table);
        $clickedCol = $schema['clickedCol'];
        if (!$clickedCol) {
            return (object)$out;
        }

        $f = $this->normalizeFilters(is_array($filters) ? $filters : array(), $schema);
        $where = array();
        $params = array();
        $cols = $schema['columns'];

        if (!empty($f['page_url']) && in_array('page_url', $cols, true)) {
            $where[] = "`page_url` LIKE %s";
            $params[] = trailingslashit($f['page_url']) . '%';
        }
        if (!empty($f['element_tag']) && in_array('element_tag', $cols, true)) {
            $where[] = "`element_tag` = %s";
            $params[] = $f['element_tag'];
        }
        if (!empty($f['user_id']) && in_array('user_id', $cols, true)) {
            $where[] = "`user_id` = %d";
            $params[] = (int)$f['user_id'];
        }
        $whereSql = empty($where) ? '' : (' AND ' . implode(' AND ', $where));

        $q1  = "SELECT COUNT(*) FROM `{$table}` WHERE 1=1 {$whereSql}";
        $q7  = "SELECT COUNT(*) FROM `{$table}` WHERE `{$clickedCol}` >= (UTC_TIMESTAMP() - INTERVAL 7 DAY) {$whereSql}";
        $q30 = "SELECT COUNT(*) FROM `{$table}` WHERE `{$clickedCol}` >= (UTC_TIMESTAMP() - INTERVAL 30 DAY) {$whereSql}";

        $out["total"]  = (int)$wpdb->get_var(!empty($params) ? $wpdb->prepare($q1,  $params) : $q1);
        $out["last7"]  = (int)$wpdb->get_var(!empty($params) ? $wpdb->prepare($q7,  $params) : $q7);
        $out["last30"] = (int)$wpdb->get_var(!empty($params) ? $wpdb->prepare($q30, $params) : $q30);

        return (object)$out;
    }
}
