<?php

declare(strict_types=1);

namespace IW8\WA\Rest;

use IW8\WA\Services\LimitsProvider;
use IW8\WA\Validation\RequestValidator;
use IW8\WA\Validation\CursorCodec;
use IW8\WA\Http\JsonResponse;
use IW8\WA\Http\ErrorFactory;

if (!defined('ABSPATH')) {
    exit;
}

final class ClicksController
{
    private LimitsProvider $limits;
    private RequestValidator $validator;
    private CursorCodec $cursor;

    public function __construct(LimitsProvider $limits, RequestValidator $validator, CursorCodec $cursor)
    {
        $this->limits    = $limits;
        $this->validator = $validator;
        $this->cursor    = $cursor;
    }

    /** MVP: valida params e retorna página vazia (sem DB ainda) */
    public function handle(\WP_REST_Request $request)
    {
        $v = $this->validator->validate($request);
        if (is_wp_error($v)) {
            return ErrorFactory::make($v->get_error_code(), $v->get_error_message(), (int)($v->get_error_data()['status'] ?? 400));
        }

        // Se vier cursor, o decode já será exercitado no próximo passo.
        if (!empty($v['using_cursor']) && isset($v['cursor_raw'])) {
            $decoded = $this->cursor->decode((string)$v['cursor_raw']);
            if (is_wp_error($decoded)) {
                return ErrorFactory::make($decoded->get_error_code(), $decoded->get_error_message(), (int)($decoded->get_error_data()['status'] ?? 400));
            }
        }

        $range = [
            'effective_since' => $v['effective_since'] ?? null,
            'effective_until' => $v['effective_until'] ?? null,
        ];

        $resp = [
            'range' => $range,
            'count' => 0,
            'items' => [],
            // Sem next_cursor quando não há dados
        ];

        return JsonResponse::ok($resp, 200);
    }
}
