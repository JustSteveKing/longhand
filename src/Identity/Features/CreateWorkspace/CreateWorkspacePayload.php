<?php

declare(strict_types=1);

namespace Longhand\Identity\Features\CreateWorkspace;

use Longhand\Identity\Handle;

final readonly class CreateWorkspacePayload
{
    public function __construct(
        public string $name,
        public Handle $handle,
        public string $displayName,
        public Handle $memberHandle,
        public string $timezone,
    ) {}
}
