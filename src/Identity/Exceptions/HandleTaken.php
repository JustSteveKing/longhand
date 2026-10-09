<?php

declare(strict_types=1);

namespace Longhand\Identity\Exceptions;

use RuntimeException;

/**
 * Rendered as `409` `resource-conflict` (RFC 0002).
 */
final class HandleTaken extends RuntimeException
{
    public function __construct(public readonly string $handle)
    {
        parent::__construct("The handle \"{$handle}\" is already taken.");
    }
}
