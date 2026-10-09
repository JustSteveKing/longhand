<?php

declare(strict_types=1);

namespace Longhand\Identity\Features\ExpireInvitations;

use Illuminate\Support\Carbon;
use Longhand\Identity\Enums\InvitationStatus;
use Longhand\Identity\Events\InvitationRevoked;
use Longhand\Identity\Models\Invitation;
use Longhand\Shared\Actions\Action;
use Longhand\Shared\Actions\NoInput;
use Longhand\Shared\Actors\AuthorisedActor;
use Longhand\Shared\Events\RecordedEvents;

/**
 * Scheduled work: marks pending invitations past their 7 days as expired,
 * firing `invitation.revoked` with `reason: expired` (RFC 0003).
 */
#[Action('invitation.expire', approvable: false)]
final readonly class ExpireInvitations
{
    public function __construct(private RecordedEvents $events) {}

    public function handle(AuthorisedActor $actor, NoInput $payload): int
    {
        $expired = Invitation::query()
            ->where('status', InvitationStatus::Pending)
            ->where('expires_at', '<=', Carbon::now())
            ->lockForUpdate()
            ->get();

        foreach ($expired as $invitation) {
            $invitation->update(['status' => InvitationStatus::Expired]);
            $this->events->record(new InvitationRevoked($invitation->workspace_id, $invitation->id, 'expired'));
        }

        return $expired->count();
    }
}
