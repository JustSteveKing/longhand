<?php

declare(strict_types=1);

namespace Longhand\Identity\Features\ReactivateMember;

use Longhand\Identity\ActingMember;
use Longhand\Identity\Enums\MemberKind;
use Longhand\Identity\Enums\MemberStatus;
use Longhand\Identity\Enums\Role;
use Longhand\Identity\Events\MemberReactivated;
use Longhand\Identity\Features\ChangeRole\MemberPayload;
use Longhand\Identity\Models\Member;
use Longhand\Shared\Actions\Action;
use Longhand\Shared\Actions\Authorisation;
use Longhand\Shared\Actions\Guarded;
use Longhand\Shared\Actors\AuthorisedActor;
use Longhand\Shared\Errors\InvalidTransition;
use Longhand\Shared\Events\RecordedEvents;

/**
 * Owners and admins bring a deactivated person back (RFC 0003). Their
 * agents stay suspended until someone resumes them. A person whose
 * account was deleted cannot come back this way; they sign up again.
 */
#[Action('member.reactivate', scope: 'members:write', humansOnly: true)]
final readonly class ReactivateMember implements Guarded
{
    public function __construct(private RecordedEvents $events) {}

    public function guard(AuthorisedActor $actor, object $payload): Authorisation
    {
        return in_array(ActingMember::of($actor)->role, [Role::Owner, Role::Admin], true)
            ? Authorisation::allow()
            : Authorisation::refuse('Only owners and admins reactivate members.');
    }

    public function handle(AuthorisedActor $actor, MemberPayload $payload): Member
    {
        $member = Member::query()
            ->where('workspace_id', ActingMember::of($actor)->workspace_id)
            ->where('kind', MemberKind::Human)
            ->findOrFail($payload->memberId);

        if ($member->status !== MemberStatus::Deactivated || $member->account_id === null) {
            throw new InvalidTransition(
                $member->account_id === null ? 'This person deleted their account.' : 'Only a deactivated member can be reactivated.',
                $member->status->value,
                [MemberStatus::Deactivated->value],
            );
        }

        $member->update(['status' => MemberStatus::Active]);
        $this->events->record(new MemberReactivated($member->workspace_id, $member->id));

        return $member;
    }
}
