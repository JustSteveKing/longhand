<?php

declare(strict_types=1);

namespace Longhand\Identity\Events;

use Longhand\Shared\Events\DomainEvent;

/**
 * Revoked by hand, or expired: `reason` is `revoked` or `expired`.
 */
final readonly class InvitationRevoked implements DomainEvent
{
    public function __construct(
        public string $workspaceId,
        public string $invitationId,
        public string $reason,
    ) {}
}
