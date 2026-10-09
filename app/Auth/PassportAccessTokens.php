<?php

declare(strict_types=1);

namespace App\Auth;

use Illuminate\Support\Facades\DB;
use Longhand\Identity\Credentials\AccessTokens;

final class PassportAccessTokens implements AccessTokens
{
    public function revokeFor(array $memberIds): void
    {
        if ($memberIds === []) {
            return;
        }

        $clients = DB::table('oauth_clients')
            ->where('owner_type', (new TokenHolder)->getMorphClass())
            ->whereIn('owner_id', $memberIds)
            ->pluck('id');

        $tokens = DB::table('oauth_access_tokens')
            ->where(fn ($query) => $query->whereIn('user_id', $memberIds)->orWhereIn('client_id', $clients));

        DB::table('oauth_refresh_tokens')->whereIn('access_token_id', (clone $tokens)->select('id'))->update(['revoked' => true]);
        $tokens->update(['revoked' => true]);
    }
}
