<?php

declare(strict_types=1);

namespace Longhand\Identity\Features\ConfigureAgent;

final readonly class AgentPayload
{
    public function __construct(public string $agentId) {}
}
