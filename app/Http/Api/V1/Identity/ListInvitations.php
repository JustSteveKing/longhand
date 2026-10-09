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
use Longhand\Identity\Enums\Role;
use Longhand\Identity\Models\Invitation;

/**
 * GET /v1/invitations: owners and admins, newest first (RFC 0003).
 */
final readonly class ListInvitations
{
    public function __construct(private Document $document, private ReadAccess $access) {}

    public function __invoke(Request $request): JsonResponse
    {
        $this->access->require(ApiActor::of($request), 'invitation.read', 'members:write');
        $member = CurrentMember::of($request);

        if (! in_array($member->role, [Role::Owner, Role::Admin], true)) {
            throw new ApiError(ErrorCode::InsufficientScope, 'Only owners and admins see invitations.');
        }

        $parameters = QueryParameters::from(
            $request,
            includes: ['invited_by'],
            sorts: ['created_at', 'expires_at'],
            defaultSort: ['-created_at'],
            paginated: true,
        );

        $page = CursorPage::of(
            Invitation::query()->where('workspace_id', $member->workspace_id),
            $parameters,
            ['created_at' => 'created_at', 'expires_at' => 'expires_at'],
            'id',
        );

        return $this->document->collection($request, $page, $parameters);
    }
}
