<?php

declare(strict_types=1);

namespace Longhand\Identity\Events;

use Longhand\Shared\Events\DomainEvent;

final readonly class MemberUpdated implements DomainEvent
{
    /**
     * @param  array<string, mixed>  $previous  The changed attributes, as they were.
     */
    public function __construct(
        public string $workspaceId,
        public string $memberId,
        public array $previous,
    ) {}
}
