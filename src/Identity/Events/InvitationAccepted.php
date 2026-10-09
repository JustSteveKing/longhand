<?php

declare(strict_types=1);

namespace Longhand\Identity\Events;

use Longhand\Shared\Events\DomainEvent;

/**
 * Conversations adds the new member to the invitation's spaces.
 */
final readonly class InvitationAccepted implements DomainEvent
{
    /**
     * @param  list<string>  $spaceIds
     */
    public function __construct(
        public string $workspaceId,
        public string $invitationId,
        public string $memberId,
        public array $spaceIds,
    ) {}
}
