<?php

declare(strict_types=1);

namespace Longhand\Identity\Events;

use Longhand\Shared\Events\DomainEvent;

/**
 * `reason` is `deactivated`, `left` or `account_deleted`. Other contexts
 * flag the member's open requests and owned threads to admins, and never
 * reassign them (RFC 0003).
 */
final readonly class MemberDeactivated implements DomainEvent
{
    /**
     * @param  list<string>  $suspendedAgentIds
     */
    public function __construct(
        public string $workspaceId,
        public string $memberId,
        public string $reason,
        public array $suspendedAgentIds,
    ) {}
}
