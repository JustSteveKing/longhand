<?php

declare(strict_types=1);

namespace Longhand\Identity\Features\UpdateWorkspace;

use Longhand\Identity\ActingMember;
use Longhand\Identity\Enums\Role;
use Longhand\Identity\Exceptions\HandleTaken;
use Longhand\Identity\Models\Workspace;
use Longhand\Shared\Actions\Action;
use Longhand\Shared\Actions\Authorisation;
use Longhand\Shared\Actions\Guarded;
use Longhand\Shared\Actors\AuthorisedActor;

/**
 * Owners and admins change the workspace's settings; only owners change
 * its handle, semantic search and subscription approval (RFC 0003,
 * RFC 0009, RFC 0010). The brief generator joins with Briefs.
 */
#[Action('workspace.update', scope: 'workspace:write', humansOnly: true)]
final readonly class UpdateWorkspace implements Guarded
{
    public function guard(AuthorisedActor $actor, object $payload): Authorisation
    {
        $member = ActingMember::of($actor);

        return match (true) {
            ! in_array($member->role, [Role::Owner, Role::Admin], true) => Authorisation::refuse('Only owners and admins change the workspace.'),
            $payload instanceof UpdateWorkspacePayload && $payload->handle !== null && $member->role !== Role::Owner => Authorisation::refuse('Only owners change the workspace handle.'),
            // Semantic search and subscription approval are an owner's call (RFC 0009, RFC 0010).
            $payload instanceof UpdateWorkspacePayload && ($payload->semanticSearch !== null || $payload->subscriptionApproval !== null) && $member->role !== Role::Owner => Authorisation::refuse('Only owners change semantic search or subscription approval.'),
            default => Authorisation::allow(),
        };
    }

    public function handle(AuthorisedActor $actor, UpdateWorkspacePayload $payload): Workspace
    {
        $workspace = Workspace::query()->findOrFail(ActingMember::of($actor)->workspace_id);

        if ($payload->handle !== null && $payload->handle->value !== $workspace->handle
            && Workspace::query()->where('handle', $payload->handle->value)->exists()) {
            throw new HandleTaken($payload->handle->value);
        }

        $workspace->fill(array_filter([
            'name' => $payload->name,
            'handle' => $payload->handle?->value,
            'default_timezone' => $payload->defaultTimezone,
            'max_agents_per_member' => $payload->maxAgentsPerMember,
            'max_assistants_per_member' => $payload->maxAssistantsPerMember,
            'brief_daily_limit' => $payload->briefDailyLimit,
            'semantic_search' => $payload->semanticSearch,
            'subscription_approval' => $payload->subscriptionApproval,
        ], fn ($value) => $value !== null))->save();

        return $workspace;
    }
}
