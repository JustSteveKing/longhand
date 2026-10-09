<?php

declare(strict_types=1);

namespace App\Http\Api\V1\Identity;

use App\Http\Api\ApiActor;
use App\Http\Api\CurrentMember;
use App\Http\Api\JsonApi\Versions;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Longhand\Identity\Features\ResendInvitation\InvitationPayload;
use Longhand\Identity\Features\RevokeInvitation\RevokeInvitation;
use Longhand\Identity\Models\Invitation;
use Longhand\Shared\Actions\ActionRunner;

final readonly class RevokeInvitationController
{
    public function __construct(private ActionRunner $runner) {}

    public function __invoke(Request $request, string $invitation): Response
    {
        $current = Invitation::query()->where('workspace_id', CurrentMember::of($request)->workspace_id)->findOrFail($invitation);

        Versions::require($request, $current);
        $this->runner->run(ApiActor::of($request), RevokeInvitation::class, new InvitationPayload($current->id));

        return response()->noContent();
    }
}
