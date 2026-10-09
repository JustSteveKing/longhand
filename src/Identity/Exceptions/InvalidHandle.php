<?php

declare(strict_types=1);

namespace Longhand\Identity\Exceptions;

use Longhand\Shared\Errors\DomainError;

final class InvalidHandle extends DomainError
{
    public function __construct(public readonly string $handle)
    {
        parent::__construct("A handle is 2 to 32 lowercase letters, digits and hyphens; \"{$handle}\" is not.");
    }

    public function errorCode(): string
    {
        return 'validation-failed';
    }
}
