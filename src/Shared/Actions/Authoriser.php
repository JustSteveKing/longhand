<?php

declare(strict_types=1);

namespace Longhand\Shared\Actions;

use Longhand\Shared\Actors\Actor;

/**
 * Decides whether an actor may take an action.
 *
 * Identity provides the real implementation: scopes, roles, agent rules
 * and approval rules (RFC 0003). Until it exists, ScopeAuthoriser checks
 * scopes alone.
 */
interface Authoriser
{
    public function authorise(Actor $actor, Action $action, object $payload): Authorisation;
}
