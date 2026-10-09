<?php

declare(strict_types=1);

namespace App\Http\Api\JsonApi;

use App\Exceptions\ErrorCode;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

/**
 * ETags and If-Match on every mutable resource (ADR 0011).
 */
final class Versions
{
    public static function etag(Model $model): string
    {
        $updated = $model->getAttribute('updated_at');
        $stamp = $updated instanceof \DateTimeInterface ? $updated->format('Uu') : '';

        return '"'.substr(hash('sha256', $model->getKey().'|'.$stamp), 0, 20).'"';
    }

    /**
     * Refuses a change unless If-Match names the current version: 428 when
     * it is missing, 412 with the current ETag when it is stale.
     */
    public static function require(Request $request, Model $model): void
    {
        $sent = $request->header('If-Match');
        $current = self::etag($model);

        if ($sent === null || $sent === '') {
            throw new ApiError(
                ErrorCode::PreconditionRequired,
                'Send If-Match with the ETag you last saw for this resource.',
                source: ['header' => 'If-Match'],
            );
        }

        $matches = array_map('trim', explode(',', $sent));

        if (! in_array($current, $matches, true) && ! in_array('*', $matches, true)) {
            throw new ApiError(
                ErrorCode::PreconditionFailed,
                'This resource has changed since you last read it. Fetch it again and retry.',
                source: ['header' => 'If-Match'],
                headers: ['ETag' => $current],
            );
        }
    }
}
