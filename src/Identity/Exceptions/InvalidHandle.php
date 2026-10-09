<?php

declare(strict_types=1);

namespace Longhand\Identity\Exceptions;

use InvalidArgumentException;

final class InvalidHandle extends InvalidArgumentException
{
    public function __construct(public readonly string $handle)
    {
        parent::__construct("A handle is 2 to 32 lowercase letters, digits and hyphens; \"{$handle}\" is not.");
    }
}
