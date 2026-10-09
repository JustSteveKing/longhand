<?php

declare(strict_types=1);

namespace Longhand\Identity\Exceptions;

use Longhand\Shared\Errors\DomainError;

final class AlreadyAMember extends DomainError
{
    public function __construct()
    {
        parent::__construct('That person is already a member of this workspace.');
    }

    public function errorCode(): string
    {
        return 'resource-conflict';
    }
}
