<?php

declare(strict_types=1);

namespace Longhand\Identity\Features\TransferAgent;

final readonly class TransferAgentPayload
{
    public function __construct(
        public string $agentId,
        public string $ownerId,
    ) {}
}
