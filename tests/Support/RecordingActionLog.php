<?php

declare(strict_types=1);

namespace Tests\Support;

use Longhand\Shared\Actions\Action;
use Longhand\Shared\Actions\ActionLog;
use Longhand\Shared\Actors\Actor;
use Longhand\Shared\Events\DomainEvent;

/**
 * Keeps what the runner logs, so a test can see which actions ran and
 * which events they recorded.
 */
final class RecordingActionLog implements ActionLog
{
    /** @var list<array{action: string, events: list<DomainEvent>}> */
    public array $allowed = [];

    /** @var list<array{action: string, reason: string}> */
    public array $refused = [];

    public function allowed(Actor $actor, Action $action, object $payload, mixed $result, array $events): void
    {
        $this->allowed[] = ['action' => $action->name, 'events' => $events];
    }

    public function refused(Actor $actor, Action $action, object $payload, string $reason): void
    {
        $this->refused[] = ['action' => $action->name, 'reason' => $reason];
    }

    /**
     * @return list<DomainEvent>
     */
    public function events(): array
    {
        return array_merge(...array_column($this->allowed, 'events'));
    }
}
