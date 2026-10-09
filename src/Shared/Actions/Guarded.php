<?php

declare(strict_types=1);

namespace Longhand\Shared\Actions;

use Longhand\Shared\Actors\AuthorisedActor;

/**
 * An Action with rules of its own about who may take it, which depend on
 * what it acts on: only the thread's owner, only the requester (ADR 0017).
 *
 * The runner calls guard() after the generic checks and before handle(),
 * inside the same transaction, and refuses the action if it says so.
 */
interface Guarded
{
    public function guard(AuthorisedActor $actor, object $payload): Authorisation;
}
