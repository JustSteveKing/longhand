<?php

declare(strict_types=1);

namespace Longhand\Identity\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Longhand\Identity\Enums\InvitationStatus;
use Longhand\Identity\Enums\Role;
use Longhand\Shared\Identifiers\HasPrefixedUlid;

/**
 * An invitation to join a workspace (RFC 0003). Valid for 7 days; resending
 * restarts the 7 days. Only a hash of its token is stored.
 *
 * @property string $id
 * @property string $workspace_id
 * @property string $email
 * @property Role $role
 * @property InvitationStatus $status
 * @property string $token_hash
 * @property list<string> $space_ids
 * @property string $invited_by_id
 * @property Carbon $expires_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
final class Invitation extends Model
{
    use HasPrefixedUlid;

    public const int VALID_FOR_DAYS = 7;

    /** @var list<string> */
    protected $guarded = [];

    /** @var list<string> */
    protected $hidden = ['token_hash'];

    /** @var array<string, mixed> */
    protected $attributes = ['space_ids' => '[]'];

    public static function idPrefix(): string
    {
        return 'inv';
    }

    public static function hashToken(string $token): string
    {
        return hash('sha256', $token);
    }

    /**
     * @return BelongsTo<Workspace, $this>
     */
    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function isPending(): bool
    {
        return $this->status === InvitationStatus::Pending && $this->expires_at->isFuture();
    }

    protected function casts(): array
    {
        return [
            'role' => Role::class,
            'status' => InvitationStatus::class,
            'space_ids' => 'array',
            'expires_at' => 'datetime',
        ];
    }
}
