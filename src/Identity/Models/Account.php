<?php

declare(strict_types=1);

namespace Longhand\Identity\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * A person's login, as Identity sees it (ADR 0015): read-only, and never
 * in the API. The web app's own User model owns sign-in on the same table.
 *
 * @property int $id
 * @property string $name
 * @property string $email
 * @property Carbon|null $email_verified_at
 */
final class Account extends Model
{
    protected $table = 'users';

    public function isVerified(): bool
    {
        return $this->email_verified_at !== null;
    }

    public function emailDomain(): string
    {
        $at = mb_strrpos($this->email, '@');

        return $at === false ? '' : mb_strtolower(mb_substr($this->email, $at + 1));
    }

    protected function casts(): array
    {
        return ['email_verified_at' => 'datetime'];
    }
}
