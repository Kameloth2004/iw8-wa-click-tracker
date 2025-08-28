<?php

declare(strict_types=1);

namespace IW8\WA\Security;

if (!defined('ABSPATH')) {
    exit;
}

final class TokenAuthenticator
{
    /** Valida o header X-IW8-Token contra a lista de tokens válidos. */
    public function validate(\WP_REST_Request $request)
    {
        $token = $this->extractToken($request);
        if (!$token) {
            return new \WP_Error(
                'missing_token',
                'Header X-IW8-Token ausente.',
                ['status' => 401]
            );
        }

        $valid = $this->getValidTokens();
        if (!empty($valid) && in_array($token, $valid, true)) {
            return true;
        }

        return new \WP_Error(
            'invalid_token',
            'Token inválido.',
            ['status' => 401]
        );
    }

    /** Extrai o token do header X-IW8-Token (case-insensitive). */
    public function extractToken(\WP_REST_Request $request): ?string
    {
        $headers = $request->get_headers();
        foreach (['x-iw8-token', 'X-IW8-Token'] as $k) {
            if (isset($headers[$k]) && is_array($headers[$k]) && isset($headers[$k][0])) {
                $t = trim((string)$headers[$k][0]);
                return $t !== '' ? $t : null;
            }
        }
        return null;
    }

    /** Últimos 4 caracteres do token (para exibir no /ping). */
    public function last4(?string $token): ?string
    {
        if (!$token) return null;
        $len = strlen($token);
        if ($len >= 4) return substr($token, -4);
        return $token;
    }

    /**
     * Retorna a lista de tokens válidos.
     * - IW8_WA_DEV_TOKEN (wp-config.php) para fácil teste local.
     * - Option 'iw8_wa_domain_token' (quando formos configurar via admin).
     * - Filtro 'iw8_wa_valid_tokens' para extensões futuras.
     */
    private function getValidTokens(): array
    {
        $tokens = [];

        if (defined('IW8_WA_DEV_TOKEN') && is_string(IW8_WA_DEV_TOKEN) && IW8_WA_DEV_TOKEN !== '') {
            $tokens[] = IW8_WA_DEV_TOKEN;
        }

        $opt = get_option('iw8_wa_domain_token');
        if (is_string($opt) && $opt !== '') {
            $tokens[] = $opt;
        }

        /**
         * Permite injetar tokens via código (mu-plugins etc.).
         * add_filter('iw8_wa_valid_tokens', fn($tks) => array_merge($tks, ['outro-token']));
         */
        $tokens = apply_filters('iw8_wa_valid_tokens', $tokens);

        // Remove duplicatas
        return array_values(array_unique(array_filter($tokens, 'is_string')));
    }
}
