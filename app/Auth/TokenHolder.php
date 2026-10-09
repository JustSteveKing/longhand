<?php

declare(strict_types=1);

namespace App\Auth;

use Illuminate\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Laravel\Passport\Contracts\OAuthenticatable;
use Laravel\Passport\HasApiTokens;

/**
 * A member as OAuth sees it: what tokens and clients belong to (ADR 0015,
 * ADR 0060). The same row as Identity's Member, kept apart so the domain
 * knows nothing of OAuth.
 *
 * @property string $id
 */
final class TokenHolder extends Model implements OAuthenticatable
{
    use Authenticatable, HasApiTokens;

    protected $table = 'members';

    protected $keyType = 'string';

    public $incrementing = false;

    public function getRememberTokenName(): string
    {
        return '';
    }
}
