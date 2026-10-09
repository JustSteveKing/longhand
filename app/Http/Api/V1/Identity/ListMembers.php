<?php

declare(strict_types=1);

namespace App\Http\Api\V1\Identity;

use App\Exceptions\ErrorCode;
use App\Http\Api\ApiActor;
use App\Http\Api\CurrentMember;
use App\Http\Api\JsonApi\ApiError;
use App\Http\Api\JsonApi\CursorPage;
use App\Http\Api\JsonApi\Document;
use App\Http\Api\JsonApi\QueryParameters;
use App\Http\Api\ReadAccess;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Longhand\Identity\Enums\MemberKind;
use Longhand\Identity\Models\Member;

/**
 * GET /v1/members: by display name unless sorted (RFC 0003).
 */
final readonly class ListMembers
{
    public function __construct(private Document $document, private ReadAccess $access) {}

    public function __invoke(Request $request): JsonResponse
    {
        $this->access->require(ApiActor::of($request), 'member.read', 'members:read');

        $parameters = QueryParameters::from(
            $request,
            includes: ['owner'],
            filters: ['kind'],
            sorts: ['display_name', 'handle', 'created_at'],
            defaultSort: ['display_name'],
            paginated: true,
        );

        $query = Member::query()->where('workspace_id', CurrentMember::of($request)->workspace_id);

        if (($kinds = $parameters->filterList('kind')) !== null) {
            $values = array_map(function (string $kind): string {
                $known = MemberKind::tryFrom($kind);

                return $known === null
                    ? throw new ApiError(ErrorCode::InvalidQueryParameter, "{$kind} is not a member kind.", source: ['parameter' => 'filter[kind]'])
                    : $known->value;
            }, $kinds);
            $query->whereIn('kind', $values);
        }

        $page = CursorPage::of($query, $parameters, [
            'display_name' => 'display_name',
            'handle' => 'handle',
            'created_at' => 'created_at',
        ], 'id');

        return $this->document->collection($request, $page, $parameters);
    }
}
