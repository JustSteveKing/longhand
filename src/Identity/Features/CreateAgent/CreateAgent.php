<?php

declare(strict_types=1);

namespace Longhand\Identity\Features\CreateAgent;

use Longhand\Identity\ActingMember;
use Longhand\Identity\Enums\MemberKind;
use Longhand\Identity\Enums\MemberStatus;
use Longhand\Identity\Events\AgentCreated;
use Longhand\Identity\Exceptions\HandleTaken;
use Longhand\Identity\Models\Member;
use Longhand\Shared\Actions\Action;
use Longhand\Shared\Actions\Authorisation;
use Longhand\Shared\Actions\Guarded;
use Longhand\Shared\Actors\AuthorisedActor;
use Longhand\Shared\Events\RecordedEvents;

/**
 * A member creates an agent they own; an owner or admin can create one
 * for any human member (RFC 0003). Its scopes stay within its owner's
 * role, and the workspace caps how many agents one member owns.
 */
#[Action('agent.create', scope: 'members:write', humansOnly: true)]
final readonly class CreateAgent implements Guarded
{
    use GovernsAgents;

    public function __construct(private RecordedEvents $events) {}

    public function guard(AuthorisedActor $actor, object $payload): Authorisation
    {
        $creator = ActingMember::of($actor);

        return match (true) {
            ! $payload instanceof CreateAgentPayload => Authorisation::refuse('Unexpected payload.'),
            $payload->ownerId !== null && $payload->ownerId !== $creator->id && ! $this->isAdmin($creator) => Authorisation::refuse('Only owners and admins create agents for someone else.'),
            default => Authorisation::allow(),
        };
    }

    public function handle(AuthorisedActor $actor, CreateAgentPayload $payload): Member
    {
        $creator = ActingMember::of($actor);
        $owner = $payload->ownerId === null || $payload->ownerId === $creator->id
            ? $creator
            : Member::query()->where('workspace_id', $creator->workspace_id)->findOrFail($payload->ownerId);

        $this->ensureActiveHuman($owner);
        $this->ensureGrantable($owner, $payload->scopes);
        $this->ensureValidRules($payload->requiresApprovalFor);
        $this->ensureUnderCap($owner);

        if (Member::query()->where('workspace_id', $owner->workspace_id)->where('handle', $payload->handle->value)->exists()) {
            throw new HandleTaken($payload->handle->value);
        }

        $agent = Member::query()->create([
            'workspace_id' => $owner->workspace_id,
            'account_id' => null,
            'kind' => MemberKind::Agent,
            'display_name' => $payload->displayName,
            'handle' => $payload->handle->value,
            'role' => null,
            'status' => MemberStatus::Active,
            'timezone' => $payload->timezone ?? $owner->timezone,
            'owner_id' => $owner->id,
            'description' => $payload->description,
            'scopes' => array_values(array_unique($payload->scopes)),
            'requires_approval_for' => array_values(array_unique($payload->requiresApprovalFor)),
            'space_ids' => array_values(array_unique($payload->spaceIds)),
            'model' => $payload->model,
        ]);

        $this->events->record(new AgentCreated($agent->workspace_id, $agent->id, $owner->id, $creator->id));

        return $agent;
    }
}
