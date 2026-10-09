<?php

declare(strict_types=1);

namespace Longhand\Identity\Events;

use Longhand\Shared\Events\DomainEvent;

/**
 * `reason` is `manual`, or `owner_deactivated` when it follows its owner.
 */
final readonly class AgentSuspended implements DomainEvent
{
    public function __construct(
        public string $workspaceId,
        public string $agentId,
        public string $reason,
    ) {}
}
