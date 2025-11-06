<?php

declare(strict_types=1);

namespace IW8\WA\Validation;

if (!defined('ABSPATH')) {
    exit;
}

final class CursorCodec
{
    public function encode(string $isoUtc, int $id): string
    {
        $json = \wp_json_encode(['t' => $isoUtc, 'i' => $id]);
        return rtrim(strtr(base64_encode($json), '+/', '-_'), '=');
    }

    /** @return array{t:string,i:int}|\WP_Error */
    public function decode(string $cursor)
    {
        $b64 = strtr($cursor, '-_', '+/');
        $b64 .= str_repeat('=', (4 - strlen($b64) % 4) % 4);
        $raw = base64_decode($b64, true);
        if ($raw === false) {
            return new \WP_Error('invalid_cursor', 'Cursor inválido (base64).', ['status' => 400]);
        }
        $data = json_decode($raw, true);
        if (!is_array($data) || !isset($data['t'], $data['i'])) {
            return new \WP_Error('invalid_cursor', 'Cursor inválido (formato).', ['status' => 400]);
        }
        $t = (string)$data['t'];
        $i = (int)$data['i'];
        if (!$this->isIsoUtcZ($t) || $i <= 0) {
            return new \WP_Error('invalid_cursor', 'Cursor inválido (conteúdo).', ['status' => 400]);
        }
        return ['t' => $t, 'i' => $i];
    }

    private function isIsoUtcZ(string $s): bool
    {
        // Aceita formato YYYY-MM-DDTHH:MM:SSZ
        return (bool)preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z$/', $s);
    }
}
