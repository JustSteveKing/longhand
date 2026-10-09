<?php

declare(strict_types=1);

namespace Longhand\Identity\Features\RotateAgentCredentials;

use Longhand\Identity\ActingMember;
use Longhand\Identity\Credentials\AgentCredentials;
use Longhand\Identity\Credentials\IssuedCredentials;
use Longhand\Identity\Features\ConfigureAgent\AgentPayload;
use Longhand\Identity\Features\CreateAgent\GovernsAgents;
use Longhand\Shared\Actions\Action;
use Longhand\Shared\Actions\Authorisation;
use Longhand\Shared\Actions\Guarded;
use Longhand\Shared\Actors\AuthorisedActor;

/**
 * Issues an agent a new client secret, shown once, and revokes its
 * tokens issued with the old one (RFC 0003).
 */
#[Action('agent.rotate_credentials', scope: 'members:write', humansOnly: true, approvable: false)]
final readonly class RotateAgentCredentials implements Guarded
{
    use GovernsAgents;

    public function __construct(private AgentCredentials $credentials) {}

    public function guard(AuthorisedActor $actor, object $payload): Authorisation
    {
        return $payload instanceof AgentPayload && $this->canManage(ActingMember::of($actor), $this->agent($actor, $payload->agentId))
            ? Authorisation::allow()
            : Authorisation::refuse('Only the agent\'s owner, or an owner or admin, can rotate its credentials.');
    }

    public function handle(AuthorisedActor $actor, AgentPayload $payload): IssuedCredentials
    {
        $agent = $this->agent($actor, $payload->agentId);

        return new IssuedCredentials($agent, $this->credentials->rotate($agent));
    }
}
