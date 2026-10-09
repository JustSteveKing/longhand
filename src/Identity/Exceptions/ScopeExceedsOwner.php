<?php

declare(strict_types=1);

namespace Longhand\Identity\Exceptions;

use Longhand\Shared\Errors\DomainError;

/**
 * An agent's scopes cannot exceed its owner's role (RFC 0003, ADR 0016).
 */
final class ScopeExceedsOwner extends DomainError
{
    /**
     * @param  list<string>  $scopes
     */
    public function __construct(public readonly array $scopes)
    {
        parent::__construct('This agent\'s owner cannot grant '.implode(', ', $scopes).'.');
    }

    public function errorCode(): string
    {
        return 'scope-exceeds-owner';
    }

    public function meta(): array
    {
        return ['scopes' => $this->scopes, 'spaces' => []];
    }
}
