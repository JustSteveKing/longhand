<?php

declare(strict_types=1);

namespace Longhand\Identity\Features\RevokeInvitation;

use Longhand\Identity\Enums\InvitationStatus;
use Longhand\Identity\Events\InvitationRevoked;
use Longhand\Identity\Exceptions\InvitationNotPending;
use Longhand\Identity\Features\ResendInvitation\InvitationPayload;
use Longhand\Identity\Features\ResendInvitation\ManagesInvitations;
use Longhand\Identity\Models\Invitation;
use Longhand\Shared\Actions\Action;
use Longhand\Shared\Actions\Guarded;
use Longhand\Shared\Actors\AuthorisedActor;
use Longhand\Shared\Events\RecordedEvents;

#[Action('invitation.revoke', scope: 'members:write', humansOnly: true)]
final readonly class RevokeInvitation implements Guarded
{
    use ManagesInvitations;

    public function __construct(private RecordedEvents $events) {}

    public function handle(AuthorisedActor $actor, InvitationPayload $payload): Invitation
    {
        $invitation = $this->invitation($actor, $payload);

        if ($invitation->status !== InvitationStatus::Pending) {
            throw new InvitationNotPending($invitation->status);
        }

        $invitation->update(['status' => InvitationStatus::Revoked]);
        $this->events->record(new InvitationRevoked($invitation->workspace_id, $invitation->id, 'revoked'));

        return $invitation;
    }
}
