<?php

declare(strict_types=1);

namespace Longhand\Identity\Exceptions;

use Longhand\Shared\Errors\DomainError;

final class EmailDomainNotAllowed extends DomainError
{
    public function errorCode(): string
    {
        return 'validation-failed';
    }
}
