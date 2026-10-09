<?php

declare(strict_types=1);

namespace Longhand\Identity\Audit;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Longhand\Identity\Models\Member;
use Longhand\Identity\Models\Workspace;
use Longhand\Shared\Actions\Action;
use Longhand\Shared\Actions\ActionLog;
use Longhand\Shared\Actors\Actor;

/**
 * Writes every action the runner runs, or refuses, to the audit log.
 */
final readonly class AuditActionLog implements ActionLog
{
    public function allowed(Actor $actor, Action $action, object $payload, mixed $result, array $events): void
    {
        $subject = $result instanceof Model ? $result : null;

        $this->write($actor, $action, AuditOutcome::Allowed, [
            'workspace_id' => $this->workspaceOf($actor, $subject),
            'subject_type' => $subject?->getTable(),
            'subject_id' => $subject?->getKey(),
        ]);
    }

    public function refused(Actor $actor, Action $action, object $payload, string $reason): void
    {
        $this->write($actor, $action, AuditOutcome::Refused, [
            'workspace_id' => $this->workspaceOf($actor, null),
            'reason' => $reason,
        ]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function write(Actor $actor, Action $action, AuditOutcome $outcome, array $attributes): void
    {
        AuditEvent::query()->create([
            'action' => $action->name,
            'outcome' => $outcome,
            'surface' => $actor->surface,
            'scope' => $action->scope,
            'actor_id' => $actor->memberId,
            'account_id' => $actor->accountId,
            'on_behalf_of_id' => $actor->onBehalfOf,
            'occurred_at' => Carbon::now(),
            ...$attributes,
        ]);
    }

    private function workspaceOf(Actor $actor, ?Model $subject): ?string
    {
        return match (true) {
            $actor->memberId !== null => Member::query()->whereKey($actor->memberId)->value('workspace_id'),
            $subject instanceof Workspace => $subject->id,
            $subject instanceof Member => $subject->workspace_id,
            default => null,
        };
    }
}
