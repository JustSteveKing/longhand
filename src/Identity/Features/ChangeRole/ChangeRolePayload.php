<?php

declare(strict_types=1);

namespace Longhand\Identity\Features\ChangeRole;

use Longhand\Identity\Enums\Role;

final readonly class ChangeRolePayload
{
    public function __construct(
        public string $memberId,
        public Role $role,
    ) {}
}
