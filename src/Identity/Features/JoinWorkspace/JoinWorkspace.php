<?php

declare(strict_types=1);

namespace Longhand\Identity\Features\JoinWorkspace;

use Longhand\Identity\ActingMember;
use Longhand\Identity\Enums\InvitationStatus;
use Longhand\Identity\Events\InvitationAccepted;
use Longhand\Identity\Events\MemberJoined;
use Longhand\Identity\Exceptions\InvitationNotPending;
use Longhand\Identity\Models\Invitation;
use Longhand\Identity\Models\Member;
use Longhand\Shared\Actions\Action;
use Longhand\Shared\Actors\AuthorisedActor;
use Longhand\Shared\Events\RecordedEvents;

/**
 * A signed-in, verified account accepts an invitation, whether or not its
 * email matches the invited address, so a work invitation can be accepted
 * from a personal account (RFC 0003). Web app only.
 */
#[Action('member.join', approvable: false, humansOnly: true, requiresMember: false)]
final readonly class JoinWorkspace
{
    use AddsMembers;

    public function __construct(private RecordedEvents $events) {}

    public function handle(AuthorisedActor $actor, JoinWorkspacePayload $payload): Member
    {
        $invitation = Invitation::query()
            ->where('token_hash', Invitation::hashToken($payload->token))
            ->lockForUpdate()
            ->firstOrFail();

        if (! $invitation->isPending()) {
            throw new InvitationNotPending(
                $invitation->status === InvitationStatus::Pending ? InvitationStatus::Expired : $invitation->status,
            );
        }

        $member = $this->addMember(ActingMember::account($actor), $invitation->workspace_id, $invitation->role, $payload->member);

        $invitation->update(['status' => InvitationStatus::Accepted]);

        $this->events->record(new InvitationAccepted($invitation->workspace_id, $invitation->id, $member->id, $invitation->space_ids));
        $this->events->record(new MemberJoined($invitation->workspace_id, $member->id, $invitation->invited_by_id));

        return $member;
    }
}
