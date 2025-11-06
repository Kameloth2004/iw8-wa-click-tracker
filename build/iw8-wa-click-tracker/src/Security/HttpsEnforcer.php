<?php

declare(strict_types=1);

namespace IW8\WA\Security;

use IW8\WA\Support\Env;

if (!defined('ABSPATH')) {
    exit;
}

final class HttpsEnforcer
{
    /**
     * Em produção exige HTTPS; em dev/local permite HTTP.
     * Retorna true se ok, ou \WP_Error se violado.
     */
    public function enforce()
    {
        if (Env::isProduction() && !is_ssl()) {
            return new \WP_Error(
                'insecure_transport',
                'HTTPS é obrigatório em produção.',
                ['status' => 400]
            );
        }
        return true;
    }
}
