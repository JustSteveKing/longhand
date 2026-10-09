<?php

declare(strict_types=1);

namespace Longhand\Identity\Models;

use Database\Factories\Identity\MemberFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Longhand\Identity\Enums\MemberKind;
use Longhand\Identity\Enums\MemberStatus;
use Longhand\Identity\Enums\Role;
use Longhand\Shared\Identifiers\HasPrefixedUlid;

/**
 * An account in a workspace, or an agent (RFC 0003, ADR 0015).
 *
 * The API only ever knows members. A person in three workspaces has one
 * account and three members, each with its own role, handle and timezone.
 *
 * @property string $id
 * @property string $workspace_id
 * @property int|null $account_id
 * @property MemberKind $kind
 * @property string $display_name
 * @property string $handle
 * @property Role|null $role
 * @property MemberStatus $status
 * @property string $timezone
 * @property string|null $owner_id
 * @property string|null $acts_on_behalf_of_id
 * @property string|null $description
 * @property list<string> $scopes
 * @property list<string> $requires_approval_for
 * @property list<string> $space_ids
 * @property array{provider: string, name: string}|null $model
 * @property bool $assistant
 * @property bool $spaces_follow_principal
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
#[UseFactory(MemberFactory::class)]
final class Member extends Model
{
    /** @use HasFactory<MemberFactory> */
    use HasFactory, HasPrefixedUlid;

    /** @var list<string> */
    protected $guarded = [];

    /**
     * The database's defaults, so a new model has them before it is reloaded.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'scopes' => '[]',
        'requires_approval_for' => '[]',
        'space_ids' => '[]',
        'assistant' => false,
        'spaces_follow_principal' => false,
    ];

    public static function idPrefix(): string
    {
        return 'mem';
    }

    /**
     * @return BelongsTo<Workspace, $this>
     */
    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    /**
     * @return BelongsTo<Member, $this>
     */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(Member::class, 'owner_id');
    }

    public function isHuman(): bool
    {
        return $this->kind === MemberKind::Human;
    }

    public function isActive(): bool
    {
        return $this->status === MemberStatus::Active;
    }

    protected function casts(): array
    {
        return [
            'kind' => MemberKind::class,
            'role' => Role::class,
            'status' => MemberStatus::class,
            'scopes' => 'array',
            'requires_approval_for' => 'array',
            'space_ids' => 'array',
            'model' => 'array',
            'assistant' => 'boolean',
            'spaces_follow_principal' => 'boolean',
        ];
    }
}
