<?php

declare(strict_types=1);

namespace Longhand\Shared\Actions;

use Longhand\Shared\Actors\Actor;
use Longhand\Shared\Actors\Surface;

/**
 * Checks the action's scope and nothing else.
 *
 * A placeholder until Identity's authoriser exists. Scheduled work runs
 * as the system and is always allowed.
 */
final readonly class ScopeAuthoriser implements Authoriser
{
    public function authorise(Actor $actor, Action $action, object $payload): Authorisation
    {
        if ($actor->surface === Surface::System || $action->scope === null) {
            return Authorisation::allow();
        }

        return $actor->hasScope($action->scope)
            ? Authorisation::allow()
            : Authorisation::refuse("The token does not have the {$action->scope} scope.");
    }
}
