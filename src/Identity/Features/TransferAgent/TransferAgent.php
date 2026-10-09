<?php

declare(strict_types=1);

namespace Longhand\Identity\Features\TransferAgent;

use LogicException;
use Longhand\Identity\ActingMember;
use Longhand\Identity\Events\AgentOwnerChanged;
use Longhand\Identity\Features\CreateAgent\GovernsAgents;
use Longhand\Identity\Models\Member;
use Longhand\Shared\Actions\Action;
use Longhand\Shared\Actions\Authorisation;
use Longhand\Shared\Actions\Guarded;
use Longhand\Shared\Actors\AuthorisedActor;
use Longhand\Shared\Events\RecordedEvents;

/**
 * Owners and admins move an agent to another human owner. Anything the new
 * owner could not grant is removed, and delegation ends, because only the
 * person an agent acts for can set it (RFC 0003).
 */
#[Action('agent.transfer', scope: 'members:write', humansOnly: true)]
final readonly class TransferAgent implements Guarded
{
    use GovernsAgents;

    public function __construct(private RecordedEvents $events) {}

    public function guard(AuthorisedActor $actor, object $payload): Authorisation
    {
        return $this->isAdmin(ActingMember::of($actor))
            ? Authorisation::allow()
            : Authorisation::refuse('Only owners and admins transfer agents.');
    }

    public function handle(AuthorisedActor $actor, TransferAgentPayload $payload): Member
    {
        $agent = $this->agent($actor, $payload->agentId);
        $newOwner = Member::query()->where('workspace_id', $agent->workspace_id)->findOrFail($payload->ownerId);
        $previousOwnerId = $agent->owner_id ?? throw new LogicException('Every agent has an owner.');

        $this->ensureActiveHuman($newOwner);
        $this->ensureUnderCap($newOwner, exceptAgentId: $agent->id);

        $dropped = $this->beyondOwner($newOwner, $agent->scopes);

        $agent->update([
            'owner_id' => $newOwner->id,
            'scopes' => array_values(array_diff($agent->scopes, $dropped)),
            'acts_on_behalf_of_id' => null,
        ]);

        $this->events->record(new AgentOwnerChanged($agent->workspace_id, $agent->id, $previousOwnerId, $newOwner->id, $dropped));

        return $agent;
    }
}
