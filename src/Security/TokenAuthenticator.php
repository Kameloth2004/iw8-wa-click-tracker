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
        if (!empty($valid)) {
            foreach ($valid as $v) {
                if (is_string($v) && hash_equals($v, $token)) {
                    return true;
                }
            }
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
        // 1) Forma oficial do WP REST (normaliza para lowercase)
        $h = $request->get_header('x-iw8-token');
        if (is_string($h) && trim($h) !== '') {
            return trim($h);
        }

        // 2) Array de headers do WP REST (chaves normalmente em lowercase)
        $headers = $request->get_headers();
        foreach (['x-iw8-token', 'x_iw8_token'] as $k) {
            if (isset($headers[$k])) {
                $val = is_array($headers[$k]) ? ($headers[$k][0] ?? '') : $headers[$k];
                $val = is_string($val) ? trim($val) : '';
                if ($val !== '') {
                    return $val;
                }
            }
        }

        // 3) Fallback direto via $_SERVER (nginx/fastcgi, apache)
        foreach (['HTTP_X_IW8_TOKEN', 'X_IW8_TOKEN'] as $k) {
            if (isset($_SERVER[$k]) && is_string($_SERVER[$k]) && trim($_SERVER[$k]) !== '') {
                return trim($_SERVER[$k]);
            }
        }

        // 4) getallheaders / apache_request_headers (variações de ambiente)
        if (function_exists('getallheaders')) {
            $all = getallheaders();
            foreach ($all as $name => $val) {
                if (is_string($name) && strtolower($name) === 'x-iw8-token' && is_string($val) && trim($val) !== '') {
                    return trim($val);
                }
            }
        }
        if (function_exists('apache_request_headers')) {
            $all = apache_request_headers();
            foreach ($all as $name => $val) {
                if (is_string($name) && strtolower($name) === 'x-iw8-token' && is_string($val) && trim($val) !== '') {
                    return trim($val);
                }
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

        if (defined('IW8_WA_DEV_TOKEN')) {
            $dev = constant('IW8_WA_DEV_TOKEN'); // evita aviso do linter
            if (is_string($dev) && $dev !== '') {
                $tokens[] = $dev;
            }
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
