<?php

declare(strict_types=1);

namespace Longhand\Shared\Identifiers;

use Illuminate\Database\Eloquent\Concerns\HasUniqueStringIds;
use Illuminate\Support\Str;

/**
 * Primary keys are prefixed ULIDs, such as `thr_01JA9X...` (ADR 0007, ADR 0068).
 *
 * The key is the identifier the API shows, stored as text. Laravel's own
 * HasUlids lowercases its ULIDs; these keep Crockford base32's capitals.
 * A model using this trait declares its prefix with idPrefix().
 */
trait HasPrefixedUlid
{
    use HasUniqueStringIds;

    abstract public static function idPrefix(): string;

    public function newUniqueId(): string
    {
        return static::idPrefix().'_'.Str::ulid();
    }

    protected function isValidUniqueId(mixed $value): bool
    {
        return is_string($value)
            && preg_match('/^'.preg_quote(static::idPrefix(), '/').'_[0-9A-HJKMNP-TV-Z]{26}$/', $value) === 1;
    }
}
