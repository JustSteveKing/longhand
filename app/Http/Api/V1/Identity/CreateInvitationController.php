<?php

declare(strict_types=1);

namespace App\Http\Api\V1\Identity;

use App\Http\Api\ApiActor;
use App\Http\Api\JsonApi\Document;
use App\Http\Api\JsonApi\QueryParameters;
use App\Http\Api\JsonApi\RequestDocument;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Longhand\Identity\Enums\Role;
use Longhand\Identity\Features\InviteMember\InviteMember;
use Longhand\Identity\Features\InviteMember\InviteMemberPayload;
use Longhand\Identity\Features\InviteMember\IssuedInvitation;
use Longhand\Shared\Actions\ActionRunner;

/**
 * POST /v1/invitations. The link's token goes only into the email, which
 * the web app sends; it is never in a response (RFC 0003).
 */
final readonly class CreateInvitationController
{
    public function __construct(private Document $document, private ActionRunner $runner) {}

    public function __invoke(Request $request): JsonResponse
    {
        $parameters = QueryParameters::from($request);
        $input = RequestDocument::from($request, 'invitations');

        $attributes = $input->validate([
            'email' => ['required', 'email:rfc', 'max:254'],
            'role' => ['required', 'in:owner,admin,member,guest'],
        ]);

        /** @var IssuedInvitation $issued */
        $issued = $this->runner->run(ApiActor::of($request), InviteMember::class, new InviteMemberPayload(
            email: $attributes['email'],
            role: Role::from($attributes['role']),
            spaceIds: $input->toMany('spaces', 'spaces'),
        ));

        return $this->document->resource($request, $issued->invitation, $parameters, 201, [
            'Location' => url('/v1/invitations/'.$issued->invitation->id),
        ]);
    }
}
