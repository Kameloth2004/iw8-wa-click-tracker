<?php

declare(strict_types=1);

namespace IW8\WA\Services;

if (!defined('ABSPATH')) {
    exit;
}

final class LimitsProvider
{
    public function getRatePerMinute(): int
    {
        return 60;
    }
    public function getMaxPageSize(): int
    {
        return 500;
    }
    public function getDefaultPageSize(): int
    {
        return 200;
    }
    public function getMaxLookbackDays(): int
    {
        return 180;
    }
    public function getCursorTtlSeconds(): int
    {
        return 86400;
    } // informativo p/ cliente

    /** Ordenação canônica informada no /ping */
    public function getCanonicalOrdering(): string
    {
        return 'clicked_at ASC, id ASC';
    }

    /** Semântica do cursor informada no /ping */
    public function getCursorSemantics(): string
    {
        return 'forward_only';
    }
}
