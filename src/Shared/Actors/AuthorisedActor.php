<?php

declare(strict_types=1);

namespace Longhand\Shared\Actors;

use Longhand\Shared\Actions\Action;

/**
 * An actor the runner has checked for one action (ADR 0065).
 *
 * Every Action's `handle()` takes one. The constructor is private and only
 * the ActionRunner creates instances, so an Action called directly, without
 * going through the runner's checks, audit entry and outbox, cannot be
 * given what it needs.
 */
final readonly class AuthorisedActor
{
    private function __construct(
        public Actor $actor,
        public Action $action,
    ) {}

    public function memberId(): ?string
    {
        return $this->actor->memberId;
    }

    public function surface(): Surface
    {
        return $this->actor->surface;
    }
}
