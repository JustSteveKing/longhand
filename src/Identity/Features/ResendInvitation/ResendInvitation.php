<?php

declare(strict_types=1);

namespace Longhand\Identity\Features\ResendInvitation;

use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Longhand\Identity\Enums\InvitationStatus;
use Longhand\Identity\Exceptions\InvitationNotPending;
use Longhand\Identity\Features\InviteMember\IssuedInvitation;
use Longhand\Identity\Models\Invitation;
use Longhand\Shared\Actions\Action;
use Longhand\Shared\Actions\Guarded;
use Longhand\Shared\Actors\AuthorisedActor;

/**
 * Sends an invitation again with a new link, and restarts its 7 days
 * (RFC 0003). An expired invitation can be resent; an accepted or revoked
 * one cannot.
 */
#[Action('invitation.resend', scope: 'members:write', humansOnly: true)]
final readonly class ResendInvitation implements Guarded
{
    use ManagesInvitations;

    public function handle(AuthorisedActor $actor, InvitationPayload $payload): IssuedInvitation
    {
        $invitation = $this->invitation($actor, $payload);

        if (! in_array($invitation->status, [InvitationStatus::Pending, InvitationStatus::Expired], true)) {
            throw new InvitationNotPending($invitation->status);
        }

        $token = Str::random(48);

        $invitation->update([
            'status' => InvitationStatus::Pending,
            'token_hash' => Invitation::hashToken($token),
            'expires_at' => Carbon::now()->addDays(Invitation::VALID_FOR_DAYS),
        ]);

        return new IssuedInvitation($invitation, $token);
    }
}
