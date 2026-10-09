<?php

declare(strict_types=1);

namespace Longhand\Identity\Features\ChangeRole;

final readonly class MemberPayload
{
    public function __construct(public string $memberId) {}
}
