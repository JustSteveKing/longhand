<?php

declare(strict_types=1);

namespace Longhand\Shared\Actions;

use RuntimeException;

/**
 * The runner refused an action. Each surface renders it its own way.
 */
final class ActionRefused extends RuntimeException
{
    public function __construct(
        public readonly Action $action,
        string $reason,
    ) {
        parent::__construct($reason);
    }
}
