<?php

declare(strict_types=1);

namespace Longhand\Identity\Features\ResendInvitation;

use Longhand\Identity\ActingMember;
use Longhand\Identity\Enums\Role;
use Longhand\Identity\Models\Invitation;
use Longhand\Shared\Actions\Authorisation;
use Longhand\Shared\Actors\AuthorisedActor;

/**
 * Resending and revoking: owners and admins, and only for invitations to
 * their own workspace; any other invitation does not exist for them.
 */
trait ManagesInvitations
{
    public function guard(AuthorisedActor $actor, object $payload): Authorisation
    {
        return in_array(ActingMember::of($actor)->role, [Role::Owner, Role::Admin], true)
            ? Authorisation::allow()
            : Authorisation::refuse('Only owners and admins manage invitations.');
    }

    private function invitation(AuthorisedActor $actor, InvitationPayload $payload): Invitation
    {
        return Invitation::query()
            ->where('workspace_id', ActingMember::of($actor)->workspace_id)
            ->findOrFail($payload->invitationId);
    }
}
