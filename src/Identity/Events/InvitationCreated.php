<?php

declare(strict_types=1);

namespace Longhand\Identity\Events;

use Longhand\Shared\Events\DomainEvent;

final readonly class InvitationCreated implements DomainEvent
{
    public function __construct(
        public string $workspaceId,
        public string $invitationId,
        public string $invitedById,
    ) {}
}
