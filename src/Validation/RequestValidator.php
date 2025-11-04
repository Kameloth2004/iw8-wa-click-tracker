<?php
// Compatível com PHP 7.0+ (sem typed props, sem union types)
declare(strict_types=1);

namespace IW8\WA\Validation;

use IW8\WA\Services\LimitsProvider;

if (!defined('ABSPATH')) {
    exit;
}

final class RequestValidator
{
    /** @var LimitsProvider */
    private $limits;

    /** @var array */
    private $allowedFields = array(
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

    public function __construct(LimitsProvider $limits)
    {
        $this->limits = $limits;
    }

    /**
     * Valida minimamente e retorna um "ok" com janela padrão de 7 dias.
     * @param \WP_REST_Request $r
     * @return array|\WP_Error
     */
    public function validate($r)
    {
        // 1) LIMIT — numérico e entre 1..max
        $rawLimit = $r->get_param('limit');
        if ($rawLimit === null || $rawLimit === '') {
            $limit = $this->limits->getDefaultPageSize();
        } else {
            if (!is_numeric($rawLimit)) {
                return new \WP_Error('invalid_limit', 'Parâmetro "limit" inválido (não numérico).', ['status' => 400]);
            }
            $limit = (int)$rawLimit;
            $max   = $this->limits->getMaxPageSize();
            if ($limit < 1 || $limit > $max) {
                return new \WP_Error(
                    'invalid_limit',
                    sprintf('O parâmetro "limit" deve estar entre 1 e %d.', $max),
                    ['status' => 400]
                );
            }
        }

        // 2) FIELDS — permissivo aqui; validação estrita no controller
        $fieldsCsv = trim((string)$r->get_param('fields'));
        if ($fieldsCsv === '') {
            $fields = $this->allowedFields;
        } else {
            $parts = [];
            foreach (explode(',', $fieldsCsv) as $p) {
                $p = trim($p);
                if ($p !== '') {
                    $parts[] = $p;
                }
            }
            $fields = array_values(array_intersect($parts, $this->allowedFields));
            if (empty($fields)) {
                $fields = $this->allowedFields;
            }
        }

        // 3) CURSOR — prioriza modo cursor quando presente
        //    Aceita "cursor" (oficial) e "next_cursor" (alias) — só usa o alias se "cursor" estiver vazio.
        $cursorRaw = $r->get_param('cursor');
        $cursorRaw = is_string($cursorRaw) ? trim($cursorRaw) : '';

        if ($cursorRaw === '') {
            $alias = $r->get_param('next_cursor'); // alias opcional
            if (is_string($alias)) {
                $alias = trim($alias);
            }
            if (!empty($alias)) {
                $cursorRaw = $alias;
            }
        }

        if ($cursorRaw !== '') {
            return [
                'using_cursor'    => true,
                'cursor_raw'      => $cursorRaw,
                'limit'           => $limit,
                'fields'          => $fields,
                'effective_since' => null,
                'effective_until' => null,
            ];
        }

        // 4) RANGE since/until — defaults de 7 dias, UTC
        $nowUtc = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $until  = $this->readIsoOrNull($r->get_param('until'));
        $since  = $this->readIsoOrNull($r->get_param('since'));

        if ($until === null) {
            $until = $nowUtc;
        }
        if ($since === null) {
            $since = $until->sub(new \DateInterval('P7D'));
        }

        return [
            'using_cursor'    => false,
            'cursor_raw'      => null,
            'limit'           => $limit,
            'fields'          => $fields,
            'effective_since' => $since->format('Y-m-d\TH:i:s\Z'),
            'effective_until' => $until->format('Y-m-d\TH:i:s\Z'),
        ];
    }

    /** @param mixed $s @return \DateTimeImmutable|null */
    private function readIsoOrNull($s)
    {
        $s = trim((string)$s);
        if ($s === '') {
            return null;
        }
        // Tenta parsear ISO básico; se falhar, retorna null (para este teste)
        $dt = \DateTimeImmutable::createFromFormat('Y-m-d\TH:i:s\Z', $s, new \DateTimeZone('UTC'));
        if ($dt instanceof \DateTimeImmutable) {
            return $dt;
        }
        return null;
    }

    /**
     * Valida a lista de fields contra um allowlist.
     * @param string|null $fieldsCsv
     * @param array $allowed
     * @return array|\WP_Error Lista final (sem duplicatas) ou WP_Error 400 se houver inválidos.
     */
    public static function validateFields($fieldsCsv, array $allowed)
    {
        $fieldsCsv = is_string($fieldsCsv) ? trim($fieldsCsv) : '';

        // Sem fields => deixe o caller decidir (retorne array vazio para sinalizar “use defaults”)
        if ($fieldsCsv === '') {
            return [];
        }

        // Parse básico
        $parts = [];
        foreach (explode(',', $fieldsCsv) as $p) {
            $p = trim($p);
            if ($p !== '') {
                $parts[] = $p;
            }
        }
        if (empty($parts)) {
            return [];
        }

        // Alias conhecidos (entrada) → nome canônico (saída)
        $aliasMap = [
            'created_at' => 'clicked_at',
        ];

        $normalized = [];
        foreach ($parts as $p) {
            $pLower = strtolower($p);
            if (isset($aliasMap[$pLower])) {
                $pLower = $aliasMap[$pLower];
            }
            $normalized[] = $pLower;
        }

        // Validar contra a lista permitida
        $invalid = array_diff($normalized, $allowed);
        if (!empty($invalid)) {
            // Reportar os nomes que o cliente enviou (já normalizados/aliased)
            $msg = 'Campos inválidos em "fields": ' . implode(', ', array_values($invalid));
            return new \WP_Error('invalid_fields', $msg, ['status' => 400]);
        }

        // Remover duplicatas e preservar ordem de primeira ocorrência
        $final = [];
        foreach ($normalized as $f) {
            if (!in_array($f, $final, true)) {
                $final[] = $f;
            }
        }

        return $final;
    }

    /**
     * Valida um datetime ISO-8601 (UTC com 'Z') — ex.: 2025-08-29T23:59:59Z
     * Retorna string normalizada Y-m-d H:i:s (UTC) ou WP_Error 400.
     * @param string|null $value
     * @param string $paramName
     * @return string|\WP_Error|null
     */
    public static function validateIsoUtc($value, string $paramName)
    {
        if ($value === null || trim((string)$value) === '') {
            return null;
        }
        $v = trim((string)$value);

        // Aceita 'YYYY-MM-DD' (expande para 00:00:00Z) ou 'YYYY-MM-DDTHH:MM:SSZ'
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $v)) {
            $v .= 'T00:00:00Z';
        }

        // Formato rígido com Z
        $dt = \DateTime::createFromFormat('Y-m-d\TH:i:s\Z', $v, new \DateTimeZone('UTC'));
        $errors = \DateTime::getLastErrors();
        if (!$dt || !empty($errors['warning_count']) || !empty($errors['error_count'])) {
            return new \WP_Error(
                'invalid_datetime',
                sprintf(
                    /* translators: 1: nome do parâmetro (since/until), 2: valor inválido */
                    __('Parâmetro %1$s inválido. Use ISO-8601 UTC, ex.: 2025-08-29T23:59:59Z (valor: %2$s)', 'iw8-wa-click-tracker'),
                    $paramName,
                    (string)$value
                ),
                array('status' => 400)
            );
        }

        // Normaliza para "Y-m-d H:i:s" (UTC) p/ SQL
        return $dt->format('Y-m-d H:i:s');
    }
}
