<?php

declare(strict_types=1);

namespace Longhand\Identity\Events;

use Longhand\Shared\Events\DomainEvent;

final readonly class MemberReactivated implements DomainEvent
{
    public function __construct(
        public string $workspaceId,
        public string $memberId,
    ) {}
}
