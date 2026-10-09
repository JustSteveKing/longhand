<?php

declare(strict_types=1);

namespace Longhand\Shared\Actions;

use Longhand\Shared\Actors\Actor;
use Longhand\Shared\Events\DomainEvent;

/**
 * Where the runner writes what happened: the audit log and the outbox.
 *
 * Identity provides the audit log (RFC 0003) and Integration the outbox
 * (ADR 0050). Until they exist, NullActionLog discards both.
 */
interface ActionLog
{
    /**
     * Inside the Action's transaction, after it has run.
     *
     * @param  list<DomainEvent>  $events
     */
    public function allowed(Actor $actor, Action $action, object $payload, array $events): void;

    /**
     * After the transaction has rolled back, on its own.
     */
    public function refused(Actor $actor, Action $action, object $payload, string $reason): void;
}
