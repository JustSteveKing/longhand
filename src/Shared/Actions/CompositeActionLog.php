<?php

declare(strict_types=1);

namespace Longhand\Shared\Actions;

use Longhand\Shared\Actors\Actor;

/**
 * Hands every entry to each log in turn: Identity's audit log now,
 * Integration's outbox when it exists.
 */
final readonly class CompositeActionLog implements ActionLog
{
    /**
     * @param  list<ActionLog>  $logs
     */
    public function __construct(private array $logs) {}

    public function allowed(Actor $actor, Action $action, object $payload, mixed $result, array $events): void
    {
        foreach ($this->logs as $log) {
            $log->allowed($actor, $action, $payload, $result, $events);
        }
    }

    public function refused(Actor $actor, Action $action, object $payload, string $reason): void
    {
        foreach ($this->logs as $log) {
            $log->refused($actor, $action, $payload, $reason);
        }
    }
}
