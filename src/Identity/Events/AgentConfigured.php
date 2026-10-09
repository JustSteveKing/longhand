<?php

declare(strict_types=1);

namespace Longhand\Identity\Events;

use Longhand\Shared\Events\DomainEvent;

/**
 * Surfaces as `agent.scopes_changed` (RFC 0010's catalogue).
 */
final readonly class AgentConfigured implements DomainEvent
{
    /**
     * @param  list<string>  $added
     * @param  list<string>  $removed
     */
    public function __construct(
        public string $workspaceId,
        public string $agentId,
        public array $added,
        public array $removed,
        public string $changedById,
    ) {}
}
