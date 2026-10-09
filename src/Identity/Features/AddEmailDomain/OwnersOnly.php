<?php

declare(strict_types=1);

namespace Longhand\Identity\Features\AddEmailDomain;

use Longhand\Identity\ActingMember;
use Longhand\Identity\Enums\Role;
use Longhand\Shared\Actions\Authorisation;
use Longhand\Shared\Actors\AuthorisedActor;

/**
 * Email domains are owner-only and web app only (RFC 0003).
 */
trait OwnersOnly
{
    public function guard(AuthorisedActor $actor, object $payload): Authorisation
    {
        return ActingMember::of($actor)->role === Role::Owner
            ? Authorisation::allow()
            : Authorisation::refuse('Only owners manage email domains.');
    }
}
