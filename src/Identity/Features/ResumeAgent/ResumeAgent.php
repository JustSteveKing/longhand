<?php

declare(strict_types=1);

namespace Longhand\Identity\Features\ResumeAgent;

use Longhand\Identity\ActingMember;
use Longhand\Identity\Enums\MemberStatus;
use Longhand\Identity\Events\AgentResumed;
use Longhand\Identity\Features\ConfigureAgent\AgentPayload;
use Longhand\Identity\Features\CreateAgent\GovernsAgents;
use Longhand\Identity\Models\Member;
use Longhand\Shared\Actions\Action;
use Longhand\Shared\Actions\Authorisation;
use Longhand\Shared\Actions\Guarded;
use Longhand\Shared\Actors\AuthorisedActor;
use Longhand\Shared\Errors\InvalidTransition;
use Longhand\Shared\Events\RecordedEvents;

/**
 * Resumes a suspended agent, once its owner is active (RFC 0003).
 */
#[Action('agent.resume', scope: 'members:write', humansOnly: true)]
final readonly class ResumeAgent implements Guarded
{
    use GovernsAgents;

    public function __construct(private RecordedEvents $events) {}

    public function guard(AuthorisedActor $actor, object $payload): Authorisation
    {
        return $payload instanceof AgentPayload && $this->canManage(ActingMember::of($actor), $this->agent($actor, $payload->agentId))
            ? Authorisation::allow()
            : Authorisation::refuse('Only the agent\'s owner, or an owner or admin, can resume it.');
    }

    public function handle(AuthorisedActor $actor, AgentPayload $payload): Member
    {
        $agent = $this->agent($actor, $payload->agentId);

        if ($agent->status !== MemberStatus::Suspended) {
            throw new InvalidTransition('Only a suspended agent can be resumed.', $agent->status->value, [MemberStatus::Suspended->value]);
        }

        $this->ensureActiveHuman(Member::query()->findOrFail($agent->owner_id));

        $agent->update(['status' => MemberStatus::Active]);
        $this->events->record(new AgentResumed($agent->workspace_id, $agent->id));

        return $agent;
    }
}
