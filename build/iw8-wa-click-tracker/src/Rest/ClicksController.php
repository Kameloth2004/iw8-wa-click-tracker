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
    /** @var LimitsProvider */
    private $limits;
    /** @var RequestValidator */
    private $validator;
    /** @var CursorCodec */
    private $cursor;
    /** @var ClickRepository */
    private $repo;

    public function __construct(
        LimitsProvider $limits,
        RequestValidator $validator,
        CursorCodec $cursor,
        ClickRepository $repo
    ) {
        $this->limits    = $limits;
        $this->validator = $validator;
        $this->cursor    = $cursor;
        $this->repo      = $repo;
    }

    /**
     * Handler do endpoint GET /clicks
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response|\WP_Error
     */
    public function handle(\WP_REST_Request $request)
    {
        // --- Rate limit (por token + rota)
        $auth  = new TokenAuthenticator();
        $token = $auth->extractToken($request);

        $rl   = new RateLimiter();
        $key  = $rl->keyFor($request->get_route(), (string)$token);
        $meta = $rl->check($key, $this->limits->getRatePerMinute(), 60);

        if (!$meta['allowed']) {
            $resp = ErrorFactory::make(
                'too_many_requests',
                'Limite de taxa excedido.',
                429,
                array(),
                $meta['retry_after_seconds']
            );
            return $rl->applyHeaders($resp, $meta);
        }

        // --- Validação base (limit e janela padrão)
        $v = $this->validator->validate($request);
        if (is_wp_error($v)) {
            $resp = ErrorFactory::make(
                $v->get_error_code(),
                $v->get_error_message(),
                (int)($v->get_error_data()['status'] ?? 400)
            );
            return $rl->applyHeaders($resp, $meta);
        }

        $limit = (int)$v['limit'];

        // --- Validação estrita: fields / since / until
        $allowedFields = array(
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

        // fields
        $fieldsChecked = RequestValidator::validateFields($request->get_param('fields'), $allowedFields);
        if (is_wp_error($fieldsChecked)) {
            $resp = ErrorFactory::make(
                $fieldsChecked->get_error_code(),
                $fieldsChecked->get_error_message(),
                (int)($fieldsChecked->get_error_data()['status'] ?? 400)
            );
            return $rl->applyHeaders($resp, $meta);
        }
        // Se vazio, usa os defaults calculados pelo validator base
        $fields = empty($fieldsChecked) ? (array)$v['fields'] : (array)$fieldsChecked;

        // since/until (validação rígida ISO-Z -> SQL; saída para o repo continua ISO-Z)
        $sinceSql = RequestValidator::validateIsoUtc($request->get_param('since'), 'since');
        if (is_wp_error($sinceSql)) {
            $resp = ErrorFactory::make(
                $sinceSql->get_error_code(),
                $sinceSql->get_error_message(),
                (int)($sinceSql->get_error_data()['status'] ?? 400)
            );
            return $rl->applyHeaders($resp, $meta);
        }
        $untilSql = RequestValidator::validateIsoUtc($request->get_param('until'), 'until');
        if (is_wp_error($untilSql)) {
            $resp = ErrorFactory::make(
                $untilSql->get_error_code(),
                $untilSql->get_error_message(),
                (int)($untilSql->get_error_data()['status'] ?? 400)
            );
            return $rl->applyHeaders($resp, $meta);
        }

        // Normaliza para ISO-8601 Z que o repositório já espera
        $sinceIso = is_string($sinceSql)
            ? gmdate('Y-m-d\TH:i:s\Z', strtotime($sinceSql . ' UTC'))
            : (string)$v['effective_since'];

        $untilIso = is_string($untilSql)
            ? gmdate('Y-m-d\TH:i:s\Z', strtotime($untilSql . ' UTC'))
            : (string)$v['effective_until'];

        // --- Cursor forward-only
        if (!empty($v['using_cursor']) && !empty($v['cursor_raw'])) {
            $decoded = $this->cursor->decode((string)$v['cursor_raw']);
            if (is_wp_error($decoded)) {
                $resp = ErrorFactory::make(
                    $decoded->get_error_code(),
                    $decoded->get_error_message(),
                    (int)($decoded->get_error_data()['status'] ?? 400)
                );
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

        // --- Sem cursor: janela since/until
        $res   = $this->repo->fetchByRange($sinceIso, $untilIso, $limit, $fields);
        $items = $res['items'];
        $last  = $res['last'];

        $payload = array(
            'range' => array(
                'effective_since' => $sinceIso,
                'effective_until' => $untilIso,
            ),
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
