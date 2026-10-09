<?php

declare(strict_types=1);

namespace Longhand\Identity\Features\DelegateAgent;

final readonly class DelegateAgentPayload
{
    /**
     * @param  bool  $actForMe  True to have the agent act for the caller, false to stop it.
     */
    public function __construct(
        public string $agentId,
        public bool $actForMe,
    ) {}
}
