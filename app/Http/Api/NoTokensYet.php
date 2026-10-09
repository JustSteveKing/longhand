<?php

declare(strict_types=1);

namespace App\Http\Api;

use Illuminate\Http\Request;
use Longhand\Shared\Actors\Actor;

/**
 * Until Passport issues tokens, no request is from anyone.
 */
final class NoTokensYet implements ApiActorResolver
{
    public function resolve(Request $request): ?Actor
    {
        return null;
    }
}
