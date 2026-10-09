<?php

declare(strict_types=1);

namespace Longhand\Identity\Models;

use Database\Factories\Identity\WorkspaceFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Longhand\Shared\Identifiers\HasPrefixedUlid;

/**
 * A team using Longhand, with its own members, spaces and settings (RFC 0003).
 *
 * @property string $id
 * @property string $name
 * @property string $handle
 * @property string $default_timezone
 * @property int $max_agents_per_member
 * @property int $max_assistants_per_member
 * @property int $brief_daily_limit
 * @property bool $semantic_search
 * @property bool $subscription_approval
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
#[UseFactory(WorkspaceFactory::class)]
final class Workspace extends Model
{
    /** @use HasFactory<WorkspaceFactory> */
    use HasFactory, HasPrefixedUlid;

    /** @var list<string> */
    protected $guarded = [];

    public static function idPrefix(): string
    {
        return 'wsp';
    }

    /**
     * @return HasMany<Member, $this>
     */
    public function members(): HasMany
    {
        return $this->hasMany(Member::class);
    }

    protected function casts(): array
    {
        return [
            'max_agents_per_member' => 'integer',
            'max_assistants_per_member' => 'integer',
            'brief_daily_limit' => 'integer',
            'semantic_search' => 'boolean',
            'subscription_approval' => 'boolean',
        ];
    }
}
