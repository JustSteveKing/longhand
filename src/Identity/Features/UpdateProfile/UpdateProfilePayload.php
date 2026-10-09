<?php

declare(strict_types=1);

namespace Longhand\Identity\Features\UpdateProfile;

use Longhand\Identity\Handle;

/**
 * What to change about yourself; null leaves it as it is.
 */
final readonly class UpdateProfilePayload
{
    public function __construct(
        public ?string $displayName = null,
        public ?Handle $handle = null,
        public ?string $timezone = null,
    ) {}
}
