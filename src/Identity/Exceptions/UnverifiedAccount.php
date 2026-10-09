<?php

declare(strict_types=1);

namespace Longhand\Identity\Exceptions;

use Longhand\Shared\Errors\DomainError;

final class UnverifiedAccount extends DomainError
{
    public function __construct()
    {
        parent::__construct('Verify your email address before creating or joining a workspace.');
    }

    public function errorCode(): string
    {
        return 'insufficient-scope';
    }
}
