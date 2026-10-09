<?php

declare(strict_types=1);

namespace Longhand\Identity\Features\DeactivateMember;

use Longhand\Identity\ActingMember;
use Longhand\Identity\Enums\MemberKind;
use Longhand\Identity\Enums\MemberStatus;
use Longhand\Identity\Enums\Role;
use Longhand\Identity\Features\ChangeRole\MemberPayload;
use Longhand\Identity\Models\Member;
use Longhand\Shared\Actions\Action;
use Longhand\Shared\Actions\Authorisation;
use Longhand\Shared\Actions\Guarded;
use Longhand\Shared\Actors\AuthorisedActor;
use Longhand\Shared\Errors\InvalidTransition;
use Longhand\Shared\Events\RecordedEvents;

/**
 * Owners and admins deactivate members; only an owner deactivates an
 * owner. An agent's owner can deactivate it too. What the member wrote
 * stays, and their agents are suspended (RFC 0003). Leaving yourself is
 * member.leave.
 */
#[Action('member.deactivate', scope: 'members:write', humansOnly: true)]
final readonly class DeactivateMember implements Guarded
{
    use EndsMemberships;

    public function __construct(private RecordedEvents $events) {}

    public function guard(AuthorisedActor $actor, object $payload): Authorisation
    {
        $by = ActingMember::of($actor);

        if (! $payload instanceof MemberPayload) {
            return Authorisation::refuse('Unexpected payload.');
        }

        $member = $this->member($actor, $payload->memberId);

        return match (true) {
            $member->id === $by->id => Authorisation::refuse('Leave the workspace instead of deactivating yourself.'),
            $member->kind === MemberKind::Agent && $member->owner_id === $by->id => Authorisation::allow(),
            ! in_array($by->role, [Role::Owner, Role::Admin], true) => Authorisation::refuse('Only owners and admins deactivate members.'),
            $member->role === Role::Owner && $by->role !== Role::Owner => Authorisation::refuse('Only an owner deactivates an owner.'),
            default => Authorisation::allow(),
        };
    }

    public function handle(AuthorisedActor $actor, MemberPayload $payload): Member
    {
        $member = $this->member($actor, $payload->memberId);

        if ($member->status === MemberStatus::Deactivated) {
            throw new InvalidTransition('This member is already deactivated.', $member->status->value, [MemberStatus::Active->value, MemberStatus::Suspended->value]);
        }

        $this->ensureNotLastOwner($member);
        $this->end($member, 'deactivated', $this->events);

        return $member;
    }

    private function member(AuthorisedActor $actor, string $memberId): Member
    {
        return Member::query()->where('workspace_id', ActingMember::of($actor)->workspace_id)->findOrFail($memberId);
    }
}
