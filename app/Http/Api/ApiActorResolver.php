<?php

declare(strict_types=1);

namespace App\Http\Api;

use Illuminate\Http\Request;
use Longhand\Shared\Actors\Actor;

/**
 * Who a REST request is from: a member, from their OAuth token (ADR 0060).
 * Returns null when there is no valid token.
 */
interface ApiActorResolver
{
    public function resolve(Request $request): ?Actor;
}
