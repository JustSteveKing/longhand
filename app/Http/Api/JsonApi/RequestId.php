<?php

declare(strict_types=1);

namespace App\Http\Api\JsonApi;

use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Every response carries X-Request-Id and meta.request_id (RFC 0002).
 */
final class RequestId
{
    public static function of(Request $request): string
    {
        $id = $request->attributes->get('request_id');

        if (! is_string($id)) {
            $sent = $request->header('X-Request-Id');
            $id = is_string($sent) && $sent !== '' && strlen($sent) <= 128 ? $sent : (string) Str::uuid();
            $request->attributes->set('request_id', $id);
        }

        return $id;
    }
}
