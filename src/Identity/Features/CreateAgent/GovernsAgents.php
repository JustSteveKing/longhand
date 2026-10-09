<?php

declare(strict_types=1);

namespace Longhand\Identity\Features\CreateAgent;

use Longhand\Identity\ActingMember;
use Longhand\Identity\Authorisation\RoleScopes;
use Longhand\Identity\Enums\MemberKind;
use Longhand\Identity\Enums\MemberStatus;
use Longhand\Identity\Enums\Role;
use Longhand\Identity\Exceptions\AgentLimitReached;
use Longhand\Identity\Exceptions\InvalidApprovalRule;
use Longhand\Identity\Exceptions\ScopeExceedsOwner;
use Longhand\Identity\Models\Member;
use Longhand\Identity\Models\Workspace;
use Longhand\Shared\Actors\AuthorisedActor;
use Longhand\Shared\Errors\InvalidTransition;

/**
 * The rules every agent lives under (RFC 0003, ADR 0016): a human owner,
 * scopes within the owner's role and never a human-only one, and a cap on
 * how many agents one member owns.
 */
trait GovernsAgents
{
    /**
     * An agent in the caller's workspace; any other is not there for them.
     */
    private function agent(AuthorisedActor $actor, string $agentId): Member
    {
        return Member::query()
            ->where('workspace_id', ActingMember::of($actor)->workspace_id)
            ->where('kind', MemberKind::Agent)
            ->findOrFail($agentId);
    }

    /**
     * An agent's owner, or an owner or admin of the workspace.
     */
    private function canManage(Member $actor, Member $agent): bool
    {
        return $agent->owner_id === $actor->id || in_array($actor->role, [Role::Owner, Role::Admin], true);
    }

    private function isAdmin(Member $member): bool
    {
        return in_array($member->role, [Role::Owner, Role::Admin], true);
    }

    /**
     * @param  list<string>  $scopes
     * @return list<string>
     */
    private function beyondOwner(Member $owner, array $scopes): array
    {
        $grantable = $owner->role === null
            ? []
            : array_diff(RoleScopes::for($owner->role), RoleScopes::HUMAN_ONLY);

        return array_values(array_diff($scopes, $grantable));
    }

    /**
     * @param  list<string>  $scopes
     */
    private function ensureGrantable(Member $owner, array $scopes): void
    {
        $beyond = $this->beyondOwner($owner, $scopes);

        if ($beyond !== []) {
            throw new ScopeExceedsOwner($beyond);
        }
    }

    private function ensureUnderCap(Member $owner, ?string $exceptAgentId = null): void
    {
        $limit = Workspace::query()->findOrFail($owner->workspace_id)->max_agents_per_member;

        $owned = Member::query()
            ->where('owner_id', $owner->id)
            ->where('kind', MemberKind::Agent)
            ->where('assistant', false)
            ->where('status', '!=', MemberStatus::Deactivated)
            ->when($exceptAgentId !== null, fn ($query) => $query->whereKeyNot($exceptAgentId))
            ->count();

        if ($owned >= $limit) {
            throw new AgentLimitReached($limit, $owned);
        }
    }

    /**
     * Approval rules name actions, `resource.verb` (ADR 0018).
     *
     * @param  list<string>  $rules
     */
    private function ensureValidRules(array $rules): void
    {
        foreach ($rules as $rule) {
            if (preg_match('/^[a-z_]+\.[a-z_]+$/', $rule) !== 1) {
                throw new InvalidApprovalRule($rule);
            }
        }
    }

    private function ensureActiveHuman(Member $member): void
    {
        if (! $member->isHuman() || ! $member->isActive() || $member->role === Role::Guest) {
            throw new InvalidTransition(
                'An agent\'s owner must be an active human member who is not a guest.',
                currentState: $member->status->value,
            );
        }
    }
}
