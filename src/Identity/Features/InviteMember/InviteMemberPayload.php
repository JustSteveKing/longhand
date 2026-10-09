<?php

declare(strict_types=1);

namespace Longhand\Identity\Features\InviteMember;

use Longhand\Identity\Enums\Role;

final readonly class InviteMemberPayload
{
    /**
     * @param  list<string>  $spaceIds  For a guest, the spaces they are added to on joining.
     */
    public function __construct(
        public string $email,
        public Role $role,
        public array $spaceIds = [],
    ) {}
}
