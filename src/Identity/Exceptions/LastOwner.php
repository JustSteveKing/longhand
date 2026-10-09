<?php

declare(strict_types=1);

namespace Longhand\Identity\Exceptions;

use Longhand\Shared\Errors\DomainError;

/**
 * A workspace always has at least one owner (RFC 0003).
 */
final class LastOwner extends DomainError
{
    /**
     * @param  list<string>  $workspaceIds  The workspaces that would be left without one.
     */
    public function __construct(public readonly array $workspaceIds)
    {
        parent::__construct('A workspace always needs an owner; promote another owner first.');
    }

    public function errorCode(): string
    {
        return 'last-owner';
    }

    public function meta(): array
    {
        return ['workspaces' => $this->workspaceIds];
    }
}
