<?php

declare(strict_types=1);

namespace IW8\WA\Rest;

use IW8\WA\Services\LimitsProvider;
use IW8\WA\Validation\RequestValidator;
use IW8\WA\Validation\CursorCodec;
use IW8\WA\Http\JsonResponse;
use IW8\WA\Http\ErrorFactory;
use IW8\WA\Repositories\ClickRepository;
use IW8\WA\Security\RateLimiter;
use IW8\WA\Security\TokenAuthenticator;

if (!defined('ABSPATH')) {
    exit;
}

final class ClicksController
{
    private $limits;
    private $validator;
    private $cursor;
    private $repo;

    public function __construct(LimitsProvider $limits, RequestValidator $validator, CursorCodec $cursor, ClickRepository $repo)
    {
        $this->limits    = $limits;
        $this->validator = $validator;
        $this->cursor    = $cursor;
        $this->repo      = $repo;
    }

    public function handle(\WP_REST_Request $request)
    {
        // Rate limit por token+rota (antes de qualquer trabalho)
        $auth  = new TokenAuthenticator();
        $token = $auth->extractToken($request);

        $rl   = new RateLimiter();
        $key  = $rl->keyFor($request->get_route(), (string)$token);
        $meta = $rl->check($key, $this->limits->getRatePerMinute(), 60);

        if (!$meta['allowed']) {
            $resp = ErrorFactory::make('too_many_requests', 'Limite de taxa excedido.', 429, array(), $meta['retry_after_seconds']);
            return $rl->applyHeaders($resp, $meta);
        }

        // Validação
        $v = $this->validator->validate($request);
        if (is_wp_error($v)) {
            $resp = ErrorFactory::make($v->get_error_code(), $v->get_error_message(), (int)($v->get_error_data()['status'] ?? 400));
            return $rl->applyHeaders($resp, $meta);
        }

        $limit  = (int)$v['limit'];
        $fields = (array)$v['fields'];

        if (!empty($v['using_cursor']) && !empty($v['cursor_raw'])) {
            $decoded = $this->cursor->decode((string)$v['cursor_raw']);
            if (is_wp_error($decoded)) {
                $resp = ErrorFactory::make($decoded->get_error_code(), $decoded->get_error_message(), (int)($decoded->get_error_data()['status'] ?? 400));
                return $rl->applyHeaders($resp, $meta);
            }

            $res   = $this->repo->fetchAfter((string)$decoded['t'], (int)$decoded['i'], $limit, $fields);
            $items = $res['items'];
            $last  = $res['last'];

            $payload = array(
                'range' => array('effective_since' => null, 'effective_until' => null),
                'count' => count($items),
                'items' => $items,
            );
            if ($last !== null && count($items) === $limit) {
                $payload['next_cursor'] = $this->cursor->encode((string)$last['t'], (int)$last['i']);
            }

            $resp = JsonResponse::ok($payload, 200);
            return $rl->applyHeaders($resp, $meta);
        }

        // Sem cursor: usa janela since/until
        $sinceIso = (string)$v['effective_since'];
        $untilIso = (string)$v['effective_until'];

        $res   = $this->repo->fetchByRange($sinceIso, $untilIso, $limit, $fields);
        $items = $res['items'];
        $last  = $res['last'];

        $payload = array(
            'range' => array('effective_since' => $sinceIso, 'effective_until' => $untilIso),
            'count' => count($items),
            'items' => $items,
        );
        if ($last !== null && count($items) === $limit) {
            $payload['next_cursor'] = $this->cursor->encode((string)$last['t'], (int)$last['i']);
        }

        $resp = JsonResponse::ok($payload, 200);
        return $rl->applyHeaders($resp, $meta);
    }
}
