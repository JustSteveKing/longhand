<?php

declare(strict_types=1);

namespace App\Http\Api\JsonApi;

use App\Exceptions\ErrorCode;
use RuntimeException;

/**
 * An error the API layer raises itself: a bad query parameter, a missing
 * If-Match, an unsupported media type. Domain refusals are DomainErrors.
 */
final class ApiError extends RuntimeException
{
    /**
     * @param  array{pointer?: string, parameter?: string, header?: string}  $source
     * @param  array<string, mixed>  $meta
     * @param  array<string, string>  $headers
     */
    public function __construct(
        public readonly ErrorCode $errorCode,
        string $detail,
        public readonly array $source = [],
        public readonly array $meta = [],
        public readonly array $headers = [],
    ) {
        parent::__construct($detail);
    }
}
