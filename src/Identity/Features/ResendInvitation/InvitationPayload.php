<?php

declare(strict_types=1);

namespace Longhand\Identity\Features\ResendInvitation;

final readonly class InvitationPayload
{
    public function __construct(public string $invitationId) {}
}
