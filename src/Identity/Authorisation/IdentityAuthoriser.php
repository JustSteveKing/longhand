<?php

declare(strict_types=1);

namespace Longhand\Identity\Authorisation;

use Longhand\Identity\Enums\MemberKind;
use Longhand\Identity\Models\Member;
use Longhand\Shared\Actions\Action;
use Longhand\Shared\Actions\Authorisation;
use Longhand\Shared\Actions\Authoriser;
use Longhand\Shared\Actors\Actor;
use Longhand\Shared\Actors\Surface;

/**
 * The checks every action gets, on every surface (ADR 0017).
 *
 * Who the caller is, whether they are active, the scope, and the rules
 * for agents. What depends on the thing being acted on, such as being a
 * thread's owner, is the Action's own guard. An agent's approval rules
 * send an action down its draft path.
 */
final readonly class IdentityAuthoriser implements Authoriser
{
    public function authorise(Actor $actor, Action $action, object $payload): Authorisation
    {
        if ($actor->surface === Surface::System) {
            return Authorisation::allow();
        }

        if ($actor->memberId === null) {
            return ! $action->requiresMember && $actor->accountId !== null
                ? Authorisation::allow()
                : Authorisation::refuse("{$action->name} needs a member of a workspace.", 'unauthorized');
        }

        $member = Member::query()->find($actor->memberId);

        if ($member === null || ! $member->isActive()) {
            return Authorisation::refuse('This member is not active in the workspace.', 'unauthorized');
        }

        if ($action->scope === null) {
            return $action->humansOnly && $member->kind === MemberKind::Agent
                ? Authorisation::refuse("Agents cannot take {$action->name}.")
                : Authorisation::allow();
        }

        if (! $actor->hasScope($action->scope)) {
            return Authorisation::refuse("{$action->name} needs the {$action->scope} scope, which this token does not have.");
        }

        return $member->kind === MemberKind::Agent
            ? $this->authoriseAgent($member, $action, $action->scope)
            : $this->authoriseHuman($member, $action, $action->scope);
    }

    private function authoriseHuman(Member $member, Action $action, string $scope): Authorisation
    {
        return $member->role !== null && RoleScopes::allows($member->role, $scope)
            ? Authorisation::allow()
            : Authorisation::refuse("Your role does not allow {$action->name}.");
    }

    /**
     * An agent can never exceed its owner, and never manages identity (ADR 0016).
     */
    private function authoriseAgent(Member $agent, Action $action, string $scope): Authorisation
    {
        if ($action->humansOnly || in_array($scope, RoleScopes::HUMAN_ONLY, true)) {
            return Authorisation::refuse("Agents cannot take {$action->name}.");
        }

        $owner = $agent->owner_id === null ? null : Member::query()->find($agent->owner_id);

        if ($owner === null || ! $owner->isActive() || $owner->role === null) {
            return Authorisation::refuse('This agent has no active owner.', 'unauthorized');
        }

        if (! RoleScopes::allows($owner->role, $scope)) {
            return Authorisation::refuse("{$action->name} is beyond what this agent's owner may do.");
        }

        // Approval rules list actions, not scopes (ADR 0018): the Action takes
        // its draft path and a person approves.
        return $action->approvable && in_array($action->name, $agent->requires_approval_for, true)
            ? Authorisation::approvalRequired()
            : Authorisation::allow();
    }
}
