<?php

declare(strict_types=1);

namespace Longhand\Identity\Events;

use Longhand\Shared\Events\DomainEvent;

/**
 * Surfaces as `member.joined` (RFC 0010's catalogue).
 */
final readonly class AgentCreated implements DomainEvent
{
    public function __construct(
        public string $workspaceId,
        public string $agentId,
        public string $ownerId,
        public string $createdById,
    ) {}
}
