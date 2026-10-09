<?php

declare(strict_types=1);

namespace Longhand\Identity\Features\JoinWorkspace;

use Longhand\Identity\Handle;

/**
 * How a person appears in the workspace they are joining.
 */
final readonly class NewMember
{
    public function __construct(
        public string $displayName,
        public Handle $handle,
        public string $timezone,
    ) {}
}
