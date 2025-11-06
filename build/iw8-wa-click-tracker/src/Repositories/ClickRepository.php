<?php

declare(strict_types=1);

namespace IW8\WA\Repositories;

if (!defined('ABSPATH')) {
    exit;
}

final class ClickRepository
{
    /** Retorna o nome da tabela preferida e, se necessário, a legada (fallback). */
    private function resolveTableName()
    {
        global $wpdb;
        $new = $wpdb->prefix . 'iw8_wa_clicks';
        $old = $wpdb->prefix . 'wa_clicks';

        if ($this->tableExists($new)) return $new;
        if ($this->tableExists($old)) return $old;
        return $new; // padrão (mesmo que ainda não exista)
    }

    private function tableExists($table)
    {
        global $wpdb;
        $like  = $wpdb->esc_like($table);
        $sql   = "SHOW TABLES LIKE %s";
        $found = $wpdb->get_var($wpdb->prepare($sql, $like));
        return is_string($found);
    }

    /** Descobre o esquema básico: colunas existentes e qual coluna usar como "clicked_at". */
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

        // coluna de data: prioridade para "clicked_at", senão "created_at"
        $clickedCol = in_array('clicked_at', $cols, true) ? 'clicked_at'
            : (in_array('created_at', $cols, true) ? 'created_at' : null);

        return array(
            'table'      => $table,
            'columns'    => $cols,
            'clickedCol' => $clickedCol, // pode ser null se não existir
        );
    }

    /** Lista de campos públicos possíveis (contrato atual). */
    private function allSelectableFields()
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

    /**
     * Gera SELECTs considerando o esquema detectado.
     * - Garante presença de id e clicked_at (com alias se necessário).
     * - Intersecta requestedFields com colunas disponíveis.
     * @return array{select:string, publicFields:array, clickedCol:string}
     */
    private function buildSelectParts($requestedFields, $schema)
    {
        $available = $schema['columns'];
        $clickedColDb = $schema['clickedCol']; // 'clicked_at' ou 'created_at' ou null
        $publicAll = $this->allSelectableFields();

        // Campos solicitados válidos
        $req = array();
        foreach ((array)$requestedFields as $f) {
            if (in_array($f, $publicAll, true)) {
                $req[] = $f;
            }
        }
        // Se vazio, devolve todos os possíveis (contrato)
        if (empty($req)) {
            $req = $publicAll;
        }

        // Vamos montar a lista SELECT real considerando colunas existentes
        $selectPieces = array();
        $ensureId = true;
        $ensureClicked = true;

        // Mapear cada campo solicitado para a coluna DB correspondente (se existir)
        foreach ($req as $f) {
            if ($f === 'clicked_at') {
                if ($clickedColDb === 'clicked_at') {
                    $selectPieces[] = '`clicked_at`';
                    $ensureClicked = false;
                } elseif ($clickedColDb === 'created_at') {
                    $selectPieces[] = '`created_at` AS `clicked_at`';
                    $ensureClicked = false;
                }
                // se não há nenhuma das duas, não adiciona (vamos lidar abaixo)
            } elseif (in_array($f, $available, true)) {
                $selectPieces[] = '`' . $f . '`';
                if ($f === 'id') $ensureId = false;
            }
        }

        // Garante id
        if ($ensureId && in_array('id', $available, true)) {
            $selectPieces[] = '`id`';
        }

        // Garante clicked_at
        if ($ensureClicked) {
            if ($clickedColDb === 'clicked_at') {
                $selectPieces[] = '`clicked_at`';
            } elseif ($clickedColDb === 'created_at') {
                $selectPieces[] = '`created_at` AS `clicked_at`';
            }
        }

        // Se ainda assim ficou vazio (esquema muito diferente), pega pelo menos id para não quebrar
        if (empty($selectPieces) && in_array('id', $available, true)) {
            $selectPieces[] = '`id`';
        }

        $select = implode(', ', $selectPieces);

        // Campos públicos a devolver (sem forçar extras além dos solicitados)
        $publicFields = $req;

        return array(
            'select'     => $select,
            'publicFields' => $publicFields,
            'clickedCol' => $clickedColDb ?: 'clicked_at', // default lógico para WHERE/ORDER
        );
    }

    /** Converte DATETIME (UTC) -> ISO-8601 Z */
    private function toIsoUtc($dbValue)
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
    public function fetchByRange($sinceIso, $untilIso, $limit, $requestedFields)
    {
        global $wpdb;

        $table = $this->resolveTableName();
        if (!$this->tableExists($table)) {
            return array('items' => array(), 'last' => null);
        }

        $schema = $this->discoverSchema($table);
        $parts  = $this->buildSelectParts($requestedFields, $schema);

        // Determina a coluna de data real para WHERE/ORDER
        $clickedColDb = $schema['clickedCol'];
        if ($clickedColDb === null) {
            // Sem coluna temporal não dá pra paginar corretamente
            return array('items' => array(), 'last' => null);
        }

        $sql = "SELECT {$parts['select']} FROM `{$table}`
                WHERE `{$clickedColDb}` >= %s AND `{$clickedColDb}` <= %s
                ORDER BY `{$clickedColDb}` ASC, `id` ASC
                LIMIT %d";

        $rows = $wpdb->get_results($wpdb->prepare($sql, $sinceIso, $untilIso, (int)$limit), ARRAY_A);
        if (!is_array($rows) || empty($rows)) {
            return array('items' => array(), 'last' => null);
        }

        $items = array();
        foreach ($rows as $r) {
            if (isset($r['clicked_at'])) {
                $r['clicked_at'] = $this->toIsoUtc($r['clicked_at']) ?: $r['clicked_at'];
            }
            // monta retorno só com os campos públicos solicitados
            $public = array();
            foreach ($parts['publicFields'] as $f) {
                if (array_key_exists($f, $r)) {
                    $public[$f] = $r[$f];
                }
            }
            if (empty($public)) {
                $public = $r;
            } // fallback extremo
            $items[] = $public;
        }

        $lastRaw = end($rows);
        $last = null;
        if ($lastRaw && isset($lastRaw['id'], $lastRaw['clicked_at'])) {
            $last = array(
                't' => $this->toIsoUtc($lastRaw['clicked_at']) ?: (string)$lastRaw['clicked_at'],
                'i' => (int)$lastRaw['id'],
            );
        }

        return array('items' => $items, 'last' => $last);
    }

    /**
     * Busca após cursor (ordenado).
     * @return array{items: array, last: array|null}
     */
    public function fetchAfter($cursorIso, $cursorId, $limit, $requestedFields)
    {
        global $wpdb;

        $table = $this->resolveTableName();
        if (!$this->tableExists($table)) {
            return array('items' => array(), 'last' => null);
        }

        $schema = $this->discoverSchema($table);
        $parts  = $this->buildSelectParts($requestedFields, $schema);

        $clickedColDb = $schema['clickedCol'];
        if ($clickedColDb === null) {
            return array('items' => array(), 'last' => null);
        }

        $sql = "SELECT {$parts['select']} FROM `{$table}`
                WHERE (`{$clickedColDb}` > %s) OR (`{$clickedColDb}` = %s AND `id` > %d)
                ORDER BY `{$clickedColDb}` ASC, `id` ASC
                LIMIT %d";

        $rows = $wpdb->get_results($wpdb->prepare($sql, $cursorIso, $cursorIso, (int)$cursorId, (int)$limit), ARRAY_A);
        if (!is_array($rows) || empty($rows)) {
            return array('items' => array(), 'last' => null);
        }

        $items = array();
        foreach ($rows as $r) {
            if (isset($r['clicked_at'])) {
                $r['clicked_at'] = $this->toIsoUtc($r['clicked_at']) ?: $r['clicked_at'];
            }
            $public = array();
            foreach ($parts['publicFields'] as $f) {
                if (array_key_exists($f, $r)) {
                    $public[$f] = $r[$f];
                }
            }
            if (empty($public)) {
                $public = $r;
            }
            $items[] = $public;
        }

        $lastRaw = end($rows);
        $last = null;
        if ($lastRaw && isset($lastRaw['id'], $lastRaw['clicked_at'])) {
            $last = array(
                't' => $this->toIsoUtc($lastRaw['clicked_at']) ?: (string)$lastRaw['clicked_at'],
                'i' => (int)$lastRaw['id'],
            );
        }

        return array('items' => $items, 'last' => $last);
    }
}
