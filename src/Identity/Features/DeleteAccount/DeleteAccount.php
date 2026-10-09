<?php

declare(strict_types=1);

namespace Longhand\Identity\Features\DeleteAccount;

use Longhand\Identity\ActingMember;
use Longhand\Identity\Credentials\AccessTokens;
use Longhand\Identity\Enums\MemberStatus;
use Longhand\Identity\Enums\Role;
use Longhand\Identity\Exceptions\LastOwner;
use Longhand\Identity\Features\DeactivateMember\EndsMemberships;
use Longhand\Identity\Models\Member;
use Longhand\Shared\Actions\Action;
use Longhand\Shared\Actions\NoInput;
use Longhand\Shared\Actors\AuthorisedActor;
use Longhand\Shared\Events\RecordedEvents;

/**
 * A person deletes their account (RFC 0003). Web app only, after entering
 * their password again. Refused while they are the last owner of any
 * workspace. Every membership ends, their agents are suspended, and the
 * email address and password are removed at once, so the address can
 * sign up again with no link to the old account. It cannot be undone.
 * Its tokens are revoked here; ending web sessions is the web app's.
 */
#[Action('account.delete', humansOnly: true, approvable: false, requiresMember: false)]
final readonly class DeleteAccount
{
    use EndsMemberships;

    public function __construct(
        private RecordedEvents $events,
        private AccessTokens $tokens,
    ) {}

    public function handle(AuthorisedActor $actor, NoInput $payload): int
    {
        $account = ActingMember::account($actor);

        $memberships = Member::query()
            ->where('account_id', $account->id)
            ->where('status', MemberStatus::Active)
            ->lockForUpdate()
            ->get();

        $blocking = array_values($memberships
            ->filter(fn (Member $member): bool => $member->role === Role::Owner && $this->isLastOwner($member))
            ->map(fn (Member $member): string => $member->workspace_id)
            ->all());

        if ($blocking !== []) {
            throw new LastOwner($blocking);
        }

        foreach ($memberships as $member) {
            $this->end($member, 'account_deleted', $this->events, $this->tokens);
        }

        $account->delete();

        return $account->id;
    }
}
