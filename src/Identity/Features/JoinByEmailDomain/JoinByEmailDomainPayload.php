<?php

declare(strict_types=1);

namespace Longhand\Identity\Features\JoinByEmailDomain;

use Longhand\Identity\Features\JoinWorkspace\NewMember;

final readonly class JoinByEmailDomainPayload
{
    public function __construct(
        public string $workspaceId,
        public NewMember $member,
    ) {}
}
