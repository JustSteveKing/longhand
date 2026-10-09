<?php

declare(strict_types=1);

namespace Longhand\Identity\Features\DelegateAgent;

use Longhand\Identity\ActingMember;
use Longhand\Identity\Events\AgentDelegationChanged;
use Longhand\Identity\Features\CreateAgent\GovernsAgents;
use Longhand\Identity\Models\Member;
use Longhand\Shared\Actions\Action;
use Longhand\Shared\Actions\Authorisation;
use Longhand\Shared\Actions\Guarded;
use Longhand\Shared\Actors\AuthorisedActor;
use Longhand\Shared\Events\RecordedEvents;

/**
 * Only the person an agent acts for sets `acts_on_behalf_of`, and only on
 * an agent they own: so it can only ever be the agent's owner (RFC 0003).
 */
#[Action('agent.delegate', scope: 'members:write', humansOnly: true, approvable: false)]
final readonly class DelegateAgent implements Guarded
{
    use GovernsAgents;

    public function __construct(private RecordedEvents $events) {}

    public function guard(AuthorisedActor $actor, object $payload): Authorisation
    {
        return $payload instanceof DelegateAgentPayload && $this->agent($actor, $payload->agentId)->owner_id === ActingMember::of($actor)->id
            ? Authorisation::allow()
            : Authorisation::refuse('Only an agent\'s owner can have it act for them.');
    }

    public function handle(AuthorisedActor $actor, DelegateAgentPayload $payload): Member
    {
        $agent = $this->agent($actor, $payload->agentId);
        $previous = $agent->acts_on_behalf_of_id;

        $agent->update(['acts_on_behalf_of_id' => $payload->actForMe ? ActingMember::of($actor)->id : null]);

        $this->events->record(new AgentDelegationChanged($agent->workspace_id, $agent->id, $agent->acts_on_behalf_of_id, $previous));

        return $agent;
    }
}
