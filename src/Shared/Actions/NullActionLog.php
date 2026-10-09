<?php

declare(strict_types=1);

namespace Longhand\Shared\Actions;

use Longhand\Shared\Actors\Actor;

/**
 * Discards everything, until the audit log and outbox exist.
 */
final readonly class NullActionLog implements ActionLog
{
    public function allowed(Actor $actor, Action $action, object $payload, mixed $result, array $events): void {}

    public function refused(Actor $actor, Action $action, object $payload, string $reason): void {}
}
