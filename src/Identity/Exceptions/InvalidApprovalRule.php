<?php

declare(strict_types=1);

namespace Longhand\Identity\Exceptions;

use Longhand\Shared\Errors\DomainError;

final class InvalidApprovalRule extends DomainError
{
    public function __construct(public readonly string $rule)
    {
        parent::__construct("Approval rules name actions, such as post.publish; \"{$rule}\" is not one.");
    }

    public function errorCode(): string
    {
        return 'validation-failed';
    }
}
