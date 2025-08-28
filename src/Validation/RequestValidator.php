<?php
declare(strict_types=1);

namespace IW8\WA\Validation;

use IW8\WA\Services\LimitsProvider;

if (!defined('ABSPATH')) { exit; }

final class RequestValidator
{
    private LimitsProvider $limits;
    /** @var string[] */
    private array $allowedFields = [
        'id','clicked_at','url','page_url','element_tag','element_text','user_agent','user_id','geo_city','geo_region'
    ];

    public function __construct(LimitsProvider $limits)
    {
        $this->limits = $limits;
    }

    /** @return array|\WP_Error */
    public function validate(\WP_REST_Request $r)
    {
        $limit = $this->readLimit($r);
        if ($limit <= 0) {
            return new \WP_Error('invalid_limit', 'Parâmetro limit deve ser > 0.', ['status' => 400]);
        }
        $max = $this->limits->getMaxPageSize();
        if ($limit > $max) {
            $limit = $max;
        }

        $cursorParam = $this->nullIfEmpty((string)$r->get_param('cursor'));
        $fieldsCsv   = $this->nullIfEmpty((string)$r->get_param('fields'));

        $fields = $this->parseFields($fieldsCsv);
        if ($fields instanceof \WP_Error) {
            return $fields;
        }

        if ($cursorParam) {
            // Com cursor, ignoramos since/until
            return [
                'using_cursor' => true,
                'cursor_raw'   => $cursorParam,
                'limit'        => $limit,
                'fields'       => $fields,
                'effective_since' => null,
                'effective_until' => null,
            ];
        }

        $since = $this->parseIsoUtc($this->nullIfEmpty((string)$r->get_param('since')));
        $until = $this->parseIsoUtc($this->nullIfEmpty((string)$r->get_param('until')));

        // Defaults: últimos 7 dias
        $now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        if ($until === null) $until = $now;
        if ($since === null) $since = $until->sub(new \DateInterval('P7D'));

        if ($since > $until) {
            return new \WP_Error('invalid_range', 'since deve ser <= until.', ['status' => 400]);
        }

        $lookbackDays = $this->limits->getMaxLookbackDays();
        $diffDays = (int)$until->diff($since)->format('%a');
        if ($diffDays > $lookbackDays) {
            return new \WP_Error('window_too_large', 'Janela solicitada excede o limite permitido.', ['status' => 400]);
        }

        return [
            'using_cursor'    => false,
            'limit'           => $limit,
            'fields'          => $fields,
            'effective_since' => $since->format('Y-m-d\TH:i:s\Z'),
            'effective_until' => $until->format('Y-m-d\TH:i:s\Z'),
        ];
    }

    /** @return string[] */
    private function parseFields(?string $csv): array|\WP_Error
    {
        if ($csv === null || $csv === '') {
            return $this->allowedFields; // default = mínimos + geo opcionais
        }
        $parts = array_values(array_filter(array_map('trim', explode(',', $csv)), fn($x) => $x !== ''));
        $invalid = array_values(array_diff($parts, $this->allowedFields));
        if (!empty($invalid)) {
            return new \WP_Error('invalid_fields', 'Campos desconhecidos: '.implode(', ', $invalid), ['status' => 400]);
        }
        return $parts;
    }

    private function readLimit(\WP_REST_Request $r): int
    {
        $raw = $r->get_param('limit');
        if ($raw === null || $raw === '') {
            return $this->limits->getDefaultPageSize();
        }
        return (int)$raw;
    }

    private function parseIsoUtc(?string $s): ?\DateTimeImmutable|\WP_Error
    {
        if ($s === null || $s === '') return null;
        if (!preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z$/', $s)) {
            return new \WP_Error('invalid_range', 'Datas devem estar em ISO-8601 UTC com Z.', ['status' => 400]);
        }
        $dt = \DateTimeImmutable::createFromFormat('Y-m-d\TH:i:s\Z', $s, new \DateTimeZone('UTC'));
        if (!$dt) {
            return new \WP_Error('invalid_range', 'Data inválida.', ['status' => 400]);
        }
        return $dt;
    }

    private function nullIfEmpty(string $s): ?string
    {
        $s = trim($s);
        return $s === '' ? null : $s;
    }
}
