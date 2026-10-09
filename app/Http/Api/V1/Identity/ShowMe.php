<?php

declare(strict_types=1);

namespace App\Http\Api\V1\Identity;

use App\Http\Api\CurrentMember;
use App\Http\Api\JsonApi\Document;
use App\Http\Api\JsonApi\QueryParameters;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * GET /v1/me: the caller's member. Any valid token (RFC 0003).
 */
final readonly class ShowMe
{
    public function __construct(private Document $document) {}

    public function __invoke(Request $request): JsonResponse
    {
        return $this->document->resource($request, CurrentMember::of($request), QueryParameters::from($request));
    }
}
