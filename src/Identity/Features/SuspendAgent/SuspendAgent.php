<?php

declare(strict_types=1);

namespace Longhand\Identity\Features\SuspendAgent;

use Longhand\Identity\ActingMember;
use Longhand\Identity\Enums\MemberStatus;
use Longhand\Identity\Events\AgentSuspended;
use Longhand\Identity\Features\ConfigureAgent\AgentPayload;
use Longhand\Identity\Features\CreateAgent\GovernsAgents;
use Longhand\Identity\Models\Member;
use Longhand\Shared\Actions\Action;
use Longhand\Shared\Actions\Authorisation;
use Longhand\Shared\Actions\Guarded;
use Longhand\Shared\Actors\AuthorisedActor;
use Longhand\Shared\Errors\InvalidTransition;
use Longhand\Shared\Events\RecordedEvents;

#[Action('agent.suspend', scope: 'members:write', humansOnly: true)]
final readonly class SuspendAgent implements Guarded
{
    use GovernsAgents;

    public function __construct(private RecordedEvents $events) {}

    public function guard(AuthorisedActor $actor, object $payload): Authorisation
    {
        return $payload instanceof AgentPayload && $this->canManage(ActingMember::of($actor), $this->agent($actor, $payload->agentId))
            ? Authorisation::allow()
            : Authorisation::refuse('Only the agent\'s owner, or an owner or admin, can suspend it.');
    }

    public function handle(AuthorisedActor $actor, AgentPayload $payload): Member
    {
        $agent = $this->agent($actor, $payload->agentId);

        if ($agent->status !== MemberStatus::Active) {
            throw new InvalidTransition('Only an active agent can be suspended.', $agent->status->value, [MemberStatus::Active->value]);
        }

        $agent->update(['status' => MemberStatus::Suspended]);
        $this->events->record(new AgentSuspended($agent->workspace_id, $agent->id, 'manual'));

        return $agent;
    }
}
