<?php

declare(strict_types=1);

namespace Longhand\Identity\Exceptions;

use Longhand\Identity\Enums\InvitationStatus;
use Longhand\Shared\Errors\DomainError;

/**
 * The invitation was accepted, revoked or has expired.
 */
final class InvitationNotPending extends DomainError
{
    public function __construct(public readonly InvitationStatus $status)
    {
        parent::__construct("This invitation is {$status->value}; ask an owner or admin of the workspace for a new one.");
    }

    public function errorCode(): string
    {
        return 'invalid-transition';
    }

    public function meta(): array
    {
        return ['current_state' => $this->status->value, 'allowed' => []];
    }
}
