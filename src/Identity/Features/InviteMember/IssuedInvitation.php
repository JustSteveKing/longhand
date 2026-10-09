<?php

declare(strict_types=1);

namespace Longhand\Identity\Features\InviteMember;

use Illuminate\Database\Eloquent\Model;
use Longhand\Identity\Models\Invitation;
use Longhand\Shared\Actions\HasSubject;

/**
 * An invitation with its token, which exists only here, once, for whoever
 * sends the email. It is never stored, logged or put in an event.
 */
final readonly class IssuedInvitation implements HasSubject
{
    public function __construct(
        public Invitation $invitation,
        #[\SensitiveParameter] public string $token,
    ) {}

    public function subject(): Model
    {
        return $this->invitation;
    }
}
