<?php

declare(strict_types=1);

namespace Longhand\Identity\Exceptions;

use Longhand\Shared\Errors\DomainError;

final class AgentLimitReached extends DomainError
{
    public function __construct(public readonly int $limit, public readonly int $owned)
    {
        parent::__construct("That member already owns {$owned} agents, and the workspace allows {$limit}.");
    }

    public function errorCode(): string
    {
        return 'agent-limit-reached';
    }

    public function meta(): array
    {
        return ['limit' => $this->limit, 'owned' => $this->owned, 'kind' => 'agent'];
    }
}
