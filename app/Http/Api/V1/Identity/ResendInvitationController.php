<?php

declare(strict_types=1);

namespace App\Http\Api\V1\Identity;

use App\Http\Api\ApiActor;
use App\Http\Api\CurrentMember;
use App\Http\Api\JsonApi\Document;
use App\Http\Api\JsonApi\QueryParameters;
use App\Http\Api\JsonApi\RequestDocument;
use App\Http\Api\JsonApi\Versions;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Longhand\Identity\Features\InviteMember\IssuedInvitation;
use Longhand\Identity\Features\ResendInvitation\InvitationPayload;
use Longhand\Identity\Features\ResendInvitation\ResendInvitation;
use Longhand\Identity\Models\Invitation;
use Longhand\Shared\Actions\ActionRunner;

/**
 * PATCH /v1/invitations/{invitation} resends it, restarting its 7 days.
 */
final readonly class ResendInvitationController
{
    public function __construct(private Document $document, private ActionRunner $runner) {}

    public function __invoke(Request $request, string $invitation): JsonResponse
    {
        $parameters = QueryParameters::from($request);
        $current = Invitation::query()->where('workspace_id', CurrentMember::of($request)->workspace_id)->findOrFail($invitation);
        RequestDocument::from($request, 'invitations', $current->id, optional: true);

        Versions::require($request, $current);

        /** @var IssuedInvitation $issued */
        $issued = $this->runner->run(ApiActor::of($request), ResendInvitation::class, new InvitationPayload($current->id));

        return $this->document->resource($request, $issued->invitation, $parameters);
    }
}
