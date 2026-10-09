<?php

declare(strict_types=1);

namespace Longhand\Identity\Exceptions;

use Longhand\Shared\Errors\DomainError;

final class InvitationAlreadyPending extends DomainError
{
    public function __construct(public readonly string $invitationId)
    {
        parent::__construct('That address already has a pending invitation; resend it instead.');
    }

    public function errorCode(): string
    {
        return 'resource-conflict';
    }

    public function meta(): array
    {
        return ['invitation' => $this->invitationId];
    }
}
