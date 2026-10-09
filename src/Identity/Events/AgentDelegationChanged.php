<?php

declare(strict_types=1);

namespace Longhand\Identity\Events;

use Longhand\Shared\Events\DomainEvent;

/**
 * Surfaces as `member.updated`, with the previous delegation.
 */
final readonly class AgentDelegationChanged implements DomainEvent
{
    public function __construct(
        public string $workspaceId,
        public string $agentId,
        public ?string $actsOnBehalfOfId,
        public ?string $previousActsOnBehalfOfId,
    ) {}
}
