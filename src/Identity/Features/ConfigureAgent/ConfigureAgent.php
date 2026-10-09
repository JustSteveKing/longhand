<?php

declare(strict_types=1);

namespace Longhand\Identity\Features\ConfigureAgent;

use Longhand\Identity\ActingMember;
use Longhand\Identity\Events\AgentConfigured;
use Longhand\Identity\Features\CreateAgent\GovernsAgents;
use Longhand\Identity\Models\Member;
use Longhand\Shared\Actions\Action;
use Longhand\Shared\Actions\Authorisation;
use Longhand\Shared\Actions\Guarded;
use Longhand\Shared\Actors\AuthorisedActor;
use Longhand\Shared\Events\RecordedEvents;

/**
 * Changes an agent's scopes, approval rules, spaces or description. Only
 * a person does this: never the agent itself, never over MCP (RFC 0003).
 */
#[Action('agent.configure', scope: 'members:write', humansOnly: true)]
final readonly class ConfigureAgent implements Guarded
{
    use GovernsAgents;

    public function __construct(private RecordedEvents $events) {}

    public function guard(AuthorisedActor $actor, object $payload): Authorisation
    {
        return $payload instanceof ConfigureAgentPayload && $this->canManage(ActingMember::of($actor), $this->agent($actor, $payload->agentId))
            ? Authorisation::allow()
            : Authorisation::refuse('Only the agent\'s owner, or an owner or admin, can change it.');
    }

    public function handle(AuthorisedActor $actor, ConfigureAgentPayload $payload): Member
    {
        $agent = $this->agent($actor, $payload->agentId);
        $owner = Member::query()->findOrFail($agent->owner_id);
        $before = $agent->scopes;

        if ($payload->scopes !== null) {
            $this->ensureGrantable($owner, $payload->scopes);
        }

        if ($payload->requiresApprovalFor !== null) {
            $this->ensureValidRules($payload->requiresApprovalFor);
        }

        $agent->fill(array_filter([
            'scopes' => $payload->scopes === null ? null : array_values(array_unique($payload->scopes)),
            'requires_approval_for' => $payload->requiresApprovalFor === null ? null : array_values(array_unique($payload->requiresApprovalFor)),
            'space_ids' => $payload->spaceIds === null ? null : array_values(array_unique($payload->spaceIds)),
            'display_name' => $payload->displayName,
            'description' => $payload->description,
            'model' => $payload->model,
        ], fn ($value) => $value !== null))->save();

        $this->events->record(new AgentConfigured(
            $agent->workspace_id,
            $agent->id,
            added: array_values(array_diff($agent->scopes, $before)),
            removed: array_values(array_diff($before, $agent->scopes)),
            changedById: ActingMember::of($actor)->id,
        ));

        return $agent;
    }
}
