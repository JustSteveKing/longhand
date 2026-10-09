<?php

declare(strict_types=1);

namespace Longhand\Identity\Features\JoinByEmailDomain;

use Illuminate\Database\Eloquent\ModelNotFoundException;
use Longhand\Identity\ActingMember;
use Longhand\Identity\Enums\Role;
use Longhand\Identity\Events\MemberJoined;
use Longhand\Identity\Exceptions\UnverifiedAccount;
use Longhand\Identity\Features\JoinWorkspace\AddsMembers;
use Longhand\Identity\Models\EmailDomain;
use Longhand\Identity\Models\Member;
use Longhand\Identity\Models\Workspace;
use Longhand\Shared\Actions\Action;
use Longhand\Shared\Actors\AuthorisedActor;
use Longhand\Shared\Events\RecordedEvents;

/**
 * An account with a verified address at one of a workspace's email
 * domains joins it as a member, never in a higher role (RFC 0003). To
 * anyone else, the workspace is not there to join.
 */
#[Action('member.join_by_domain', approvable: false, humansOnly: true, requiresMember: false)]
final readonly class JoinByEmailDomain
{
    use AddsMembers;

    public function __construct(private RecordedEvents $events) {}

    public function handle(AuthorisedActor $actor, JoinByEmailDomainPayload $payload): Member
    {
        $account = ActingMember::account($actor);

        if (! $account->isVerified()) {
            throw new UnverifiedAccount;
        }

        $joinable = EmailDomain::query()
            ->where('workspace_id', $payload->workspaceId)
            ->where('domain', $account->emailDomain())
            ->exists();

        if (! $joinable) {
            throw (new ModelNotFoundException)->setModel(Workspace::class, [$payload->workspaceId]);
        }

        $member = $this->addMember($account, $payload->workspaceId, Role::Member, $payload->member);

        $this->events->record(new MemberJoined($payload->workspaceId, $member->id));

        return $member;
    }
}
