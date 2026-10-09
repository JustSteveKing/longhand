<?php

declare(strict_types=1);

namespace Longhand\Identity\Features\DeactivateMember;

use Longhand\Identity\Credentials\AccessTokens;
use Longhand\Identity\Enums\MemberKind;
use Longhand\Identity\Enums\MemberStatus;
use Longhand\Identity\Enums\Role;
use Longhand\Identity\Events\AgentSuspended;
use Longhand\Identity\Events\MemberDeactivated;
use Longhand\Identity\Exceptions\LastOwner;
use Longhand\Identity\Models\Member;
use Longhand\Shared\Events\RecordedEvents;

/**
 * Deactivating, leaving and deleting an account all end a membership the
 * same way: never the last owner, the member's agents are suspended until
 * someone transfers or deactivates them, and all their tokens are revoked
 * (RFC 0003).
 */
trait EndsMemberships
{
    private function ensureNotLastOwner(Member $member): void
    {
        if ($member->role === Role::Owner && $this->isLastOwner($member)) {
            throw new LastOwner([$member->workspace_id]);
        }
    }

    private function isLastOwner(Member $member): bool
    {
        return ! Member::query()
            ->where('workspace_id', $member->workspace_id)
            ->where('role', Role::Owner)
            ->where('status', MemberStatus::Active)
            ->whereKeyNot($member->id)
            ->exists();
    }

    private function end(Member $member, string $reason, RecordedEvents $events, AccessTokens $tokens): void
    {
        $member->update(['status' => MemberStatus::Deactivated]);

        $agents = Member::query()
            ->where('owner_id', $member->id)
            ->where('kind', MemberKind::Agent)
            ->where('status', MemberStatus::Active)
            ->get();

        foreach ($agents as $agent) {
            $agent->update(['status' => MemberStatus::Suspended]);
            $events->record(new AgentSuspended($agent->workspace_id, $agent->id, 'owner_deactivated'));
        }

        // Their tokens, and their agents', stop working at once.
        $tokens->revokeFor([$member->id, ...array_values($agents->map(fn (Member $agent): string => $agent->id)->all())]);

        $events->record(new MemberDeactivated(
            $member->workspace_id,
            $member->id,
            $reason,
            array_values($agents->map(fn (Member $agent): string => $agent->id)->all()),
        ));
    }
}
