<?php

declare(strict_types=1);

namespace App\Http\Api\V1\Identity;

use App\Http\Api\ApiActor;
use App\Http\Api\CurrentMember;
use App\Http\Api\JsonApi\Document;
use App\Http\Api\JsonApi\QueryParameters;
use App\Http\Api\ReadAccess;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Longhand\Identity\Models\Member;

final readonly class ShowMember
{
    public function __construct(private Document $document, private ReadAccess $access) {}

    public function __invoke(Request $request, string $member): JsonResponse
    {
        $this->access->require(ApiActor::of($request), 'member.read', 'members:read');

        return $this->document->resource(
            $request,
            Member::query()->where('workspace_id', CurrentMember::of($request)->workspace_id)->findOrFail($member),
            QueryParameters::from($request, includes: ['owner']),
        );
    }
}
