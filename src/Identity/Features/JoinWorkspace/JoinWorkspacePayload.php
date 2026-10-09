<?php

declare(strict_types=1);

namespace Longhand\Identity\Features\JoinWorkspace;

final readonly class JoinWorkspacePayload
{
    public function __construct(
        #[\SensitiveParameter] public string $token,
        public NewMember $member,
    ) {}
}
