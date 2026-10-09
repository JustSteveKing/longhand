<?php

declare(strict_types=1);

namespace Longhand\Identity\Events;

use Longhand\Shared\Events\DomainEvent;

/**
 * Conversations reacts by creating the `General` space, and Briefs by
 * creating the built-in `Longhand` generator (RFC 0003, RFC 0007).
 */
final readonly class WorkspaceCreated implements DomainEvent
{
    public function __construct(
        public string $workspaceId,
        public string $ownerId,
    ) {}
}
