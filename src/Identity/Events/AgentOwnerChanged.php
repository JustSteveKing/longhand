<?php

declare(strict_types=1);

namespace Longhand\Identity\Events;

use Longhand\Shared\Events\DomainEvent;

final readonly class AgentOwnerChanged implements DomainEvent
{
    /**
     * @param  list<string>  $droppedScopes  What the new owner could not grant.
     */
    public function __construct(
        public string $workspaceId,
        public string $agentId,
        public string $previousOwnerId,
        public string $ownerId,
        public array $droppedScopes,
    ) {}
}
