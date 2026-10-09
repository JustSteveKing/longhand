<?php

declare(strict_types=1);

namespace Longhand\Identity\Features\ChangeRole;

use Longhand\Identity\ActingMember;
use Longhand\Identity\Enums\MemberKind;
use Longhand\Identity\Enums\Role;
use Longhand\Identity\Events\MemberUpdated;
use Longhand\Identity\Features\DeactivateMember\EndsMemberships;
use Longhand\Identity\Models\Member;
use Longhand\Shared\Actions\Action;
use Longhand\Shared\Actions\Authorisation;
use Longhand\Shared\Actions\Guarded;
use Longhand\Shared\Actors\AuthorisedActor;
use Longhand\Shared\Events\RecordedEvents;

/**
 * Owners and admins change roles; only owners make or unmake owners, and a
 * workspace never loses its last owner (RFC 0003).
 */
#[Action('member.change_role', scope: 'members:write', humansOnly: true)]
final readonly class ChangeRole implements Guarded
{
    use EndsMemberships;

    public function __construct(private RecordedEvents $events) {}

    public function guard(AuthorisedActor $actor, object $payload): Authorisation
    {
        $changer = ActingMember::of($actor);

        if (! $payload instanceof ChangeRolePayload || ! in_array($changer->role, [Role::Owner, Role::Admin], true)) {
            return Authorisation::refuse('Only owners and admins change roles.');
        }

        $member = $this->member($actor, $payload->memberId);

        return ($payload->role === Role::Owner || $member->role === Role::Owner) && $changer->role !== Role::Owner
            ? Authorisation::refuse('Only owners make or unmake owners.')
            : Authorisation::allow();
    }

    public function handle(AuthorisedActor $actor, ChangeRolePayload $payload): Member
    {
        $member = $this->member($actor, $payload->memberId);
        $previous = $member->role;

        if ($previous === Role::Owner && $payload->role !== Role::Owner) {
            $this->ensureNotLastOwner($member);
        }

        $member->update(['role' => $payload->role]);
        $this->events->record(new MemberUpdated($member->workspace_id, $member->id, ['role' => $previous?->value]));

        return $member;
    }

    private function member(AuthorisedActor $actor, string $memberId): Member
    {
        return Member::query()
            ->where('workspace_id', ActingMember::of($actor)->workspace_id)
            ->where('kind', MemberKind::Human)
            ->findOrFail($memberId);
    }
}
