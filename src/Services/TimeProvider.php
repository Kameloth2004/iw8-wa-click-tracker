<?php

declare(strict_types=1);

namespace IW8\WA\Services;

if (!defined('ABSPATH')) {
    exit;
}

final class TimeProvider
{
    /** Retorna agora em UTC no formato ISO-8601 com 'Z'. */
    public function nowIsoUtc(): string
    {
        $dt = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        return $dt->format('Y-m-d\TH:i:s\Z');
    }
}
