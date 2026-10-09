<?php

declare(strict_types=1);

namespace Longhand\Identity\Audit;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use LogicException;
use Longhand\Shared\Actors\Surface;
use Longhand\Shared\Identifiers\HasPrefixedUlid;

/**
 * One entry in the audit log (RFC 0003, ADR 0021).
 *
 * Append-only: nothing, an owner included, can change or remove an entry.
 * A refused attempt is recorded with what was attempted and no changes.
 *
 * @property string $id
 * @property string|null $workspace_id
 * @property string $action
 * @property AuditOutcome $outcome
 * @property Surface $surface
 * @property string|null $scope
 * @property string|null $actor_id
 * @property int|null $account_id
 * @property string|null $on_behalf_of_id
 * @property string|null $subject_type
 * @property string|null $subject_id
 * @property array<string, mixed>|null $changes
 * @property string|null $reason
 * @property Carbon $occurred_at
 */
final class AuditEvent extends Model
{
    use HasPrefixedUlid;

    public $timestamps = false;

    /** @var list<string> */
    protected $guarded = [];

    public static function idPrefix(): string
    {
        return 'aud';
    }

    protected static function booted(): void
    {
        self::updating(fn (): never => throw new LogicException('Audit events are never changed.'));
        self::deleting(fn (): never => throw new LogicException('Audit events are never deleted.'));
    }

    protected function casts(): array
    {
        return [
            'outcome' => AuditOutcome::class,
            'surface' => Surface::class,
            'changes' => 'array',
            'occurred_at' => 'datetime',
        ];
    }
}
