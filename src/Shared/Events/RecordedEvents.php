<?php

declare(strict_types=1);

namespace Longhand\Shared\Events;

/**
 * The events recorded during one Action run.
 *
 * Actions record into it; the runner hands what was recorded to the outbox
 * before the transaction commits, and clears it whatever happens.
 */
final class RecordedEvents
{
    /** @var list<DomainEvent> */
    private array $events = [];

    public function record(DomainEvent $event): void
    {
        $this->events[] = $event;
    }

    /**
     * @return list<DomainEvent>
     */
    public function release(): array
    {
        $events = $this->events;
        $this->events = [];

        return $events;
    }
}
