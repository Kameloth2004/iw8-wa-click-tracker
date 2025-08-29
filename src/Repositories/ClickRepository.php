<?php

declare(strict_types=1);

namespace IW8\WA\Repositories;

if (!defined('ABSPATH')) {
    exit;
}

final class ClickRepository
{
    /** Nome da tabela (ajuste se seu plugin usar outro nome) */
    private function tableName(): string
    {
        global $wpdb;
        return $wpdb->prefix . 'iw8_wa_clicks';
    }

    /** Verifica se a tabela existe. Em caso negativo, retornamos vazio. */
    private function tableExists(): bool
    {
        global $wpdb;
        $table = $this->tableName();
        $like  = $wpdb->esc_like($table);
        $sql   = "SHOW TABLES LIKE %s";
        $found = $wpdb->get_var($wpdb->prepare($sql, $like));
        return is_string($found);
    }

    /** Lista completa de campos suportados no SELECT */
    private function allSelectableFields(): array
    {
        return array(
            'id',
            'clicked_at',
            'url',
            'page_url',
            'element_tag',
            'element_text',
            'user_agent',
            'user_id',
            'geo_city',
            'geo_region'
        );
    }

    /** Retorna lista de colunas para SELECT, sempre incluindo id e clicked_at para cursor/ordenação */
    private function selectColumns(array $requestedFields): array
    {
        $all  = $this->allSelectableFields();
        $req  = array_values(array_intersect($requestedFields, $all));
        // Garante presença de id e clicked_at para cursor/ordenação
        if (!in_array('id', $req, true)) {
            $req[] = 'id';
        }
        if (!in_array('clicked_at', $req, true)) {
            $req[] = 'clicked_at';
        }
        // Unicos e na mesma ordem estável
        $unique = array();
        foreach ($req as $f) {
            if (!in_array($f, $unique, true)) {
                $unique[] = $f;
            }
        }
        return $unique;
    }

    /** Converte DATETIME (UTC) -> ISO-8601 com Z */
    private function toIsoUtc($dbValue): ?string
    {
        if (!$dbValue) return null;
        $ts = strtotime((string)$dbValue);
        if ($ts === false) return null;
        return gmdate('Y-m-d\TH:i:s\Z', $ts);
    }

    /**
     * Busca por janela since/until (ordenado) — retorna itens e última tupla para next_cursor.
     * @return array{items: array, last: array|null}
     */
    public function fetchByRange(string $sinceIso, string $untilIso, int $limit, array $requestedFields): array
    {
        global $wpdb;
        $result = array('items' => array(), 'last' => null);
        if (!$this->tableExists()) {
            return $result;
        }

        $cols   = $this->selectColumns($requestedFields);
        $select = implode(', ', array_map(function ($c) {
            return '`' . $c . '`';
        }, $cols));
        $table  = $this->tableName();

        // WHERE clicked_at BETWEEN since AND until
        $sql = "SELECT $select FROM `$table`
                WHERE `clicked_at` >= %s AND `clicked_at` <= %s
                ORDER BY `clicked_at` ASC, `id` ASC
                LIMIT %d";

        $rows = $wpdb->get_results($wpdb->prepare($sql, $sinceIso, $untilIso, $limit), ARRAY_A);
        if (!is_array($rows) || empty($rows)) {
            return $result;
        }

        // Constrói items públicos conforme requestedFields (sem forçar id/clicked_at se não pedidos),
        // mas preserva a última tupla para o cursor.
        $items = array();
        foreach ($rows as $r) {
            if (isset($r['clicked_at'])) {
                $r['clicked_at'] = $this->toIsoUtc($r['clicked_at']) ?: $r['clicked_at'];
            }
            // Monta o item só com os campos solicitados originalmente
            $public = array();
            foreach ($requestedFields as $f) {
                if (array_key_exists($f, $r)) {
                    $public[$f] = $r[$f];
                }
            }
            // Se nenhum campo foi explicitamente pedido (caso de fallback), devolve tudo
            if (empty($requestedFields)) {
                $public = $r;
            }
            $items[] = $public;
        }

        // Última tupla para next_cursor (usa a linha crua)
        $lastRaw = end($rows);
        if ($lastRaw && isset($lastRaw['id'], $lastRaw['clicked_at'])) {
            $result['last'] = array(
                't' => $this->toIsoUtc($lastRaw['clicked_at']) ?: (string)$lastRaw['clicked_at'],
                'i' => (int)$lastRaw['id'],
            );
        }

        $result['items'] = $items;
        return $result;
    }

    /**
     * Busca após cursor (ordenado) — retorna itens e última tupla.
     * @return array{items: array, last: array|null}
     */
    public function fetchAfter(string $cursorIso, int $cursorId, int $limit, array $requestedFields): array
    {
        global $wpdb;
        $result = array('items' => array(), 'last' => null);
        if (!$this->tableExists()) {
            return $result;
        }

        $cols   = $this->selectColumns($requestedFields);
        $select = implode(', ', array_map(function ($c) {
            return '`' . $c . '`';
        }, $cols));
        $table  = $this->tableName();

        // WHERE (clicked_at > t) OR (clicked_at = t AND id > i)
        $sql = "SELECT $select FROM `$table`
                WHERE (`clicked_at` > %s) OR (`clicked_at` = %s AND `id` > %d)
                ORDER BY `clicked_at` ASC, `id` ASC
                LIMIT %d";

        $rows = $wpdb->get_results($wpdb->prepare($sql, $cursorIso, $cursorIso, $cursorId, $limit), ARRAY_A);
        if (!is_array($rows) || empty($rows)) {
            return $result;
        }

        $items = array();
        foreach ($rows as $r) {
            if (isset($r['clicked_at'])) {
                $r['clicked_at'] = $this->toIsoUtc($r['clicked_at']) ?: $r['clicked_at'];
            }
            $public = array();
            foreach ($requestedFields as $f) {
                if (array_key_exists($f, $r)) {
                    $public[$f] = $r[$f];
                }
            }
            if (empty($requestedFields)) {
                $public = $r;
            }
            $items[] = $public;
        }

        $lastRaw = end($rows);
        if ($lastRaw && isset($lastRaw['id'], $lastRaw['clicked_at'])) {
            $result['last'] = array(
                't' => $this->toIsoUtc($lastRaw['clicked_at']) ?: (string)$lastRaw['clicked_at'],
                'i' => (int)$lastRaw['id'],
            );
        }

        $result['items'] = $items;
        return $result;
    }
}
