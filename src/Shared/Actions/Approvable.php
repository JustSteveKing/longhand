<?php

declare(strict_types=1);

namespace Longhand\Shared\Actions;

use Longhand\Shared\Actors\AuthorisedActor;

/**
 * An Action with a draft path, taken when an agent's approval rules apply
 * (ADR 0018): the pending object is created and waits for a person.
 */
interface Approvable
{
    public function draft(AuthorisedActor $actor, object $payload): mixed;
}
