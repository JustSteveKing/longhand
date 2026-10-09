<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Http\Api\ApiActorResolver;
use Illuminate\Http\Request;
use Longhand\Identity\Authorisation\RoleScopes;
use Longhand\Identity\Enums\Role;
use Longhand\Identity\Models\Member;
use Longhand\Shared\Actors\Actor;
use Longhand\Shared\Actors\Surface;

/**
 * Stands in for OAuth tokens in tests: `Bearer test:<member id>` is that
 * member, with their role's scopes, or those in `test:<id>:<scope,scope>`.
 */
final class FakeTokens implements ApiActorResolver
{
    public function resolve(Request $request): ?Actor
    {
        if (preg_match('/^Bearer test:([^:]+)(?::(.*))?$/', (string) $request->header('Authorization'), $matches) !== 1) {
            return null;
        }

        $member = Member::query()->find($matches[1]);

        if ($member === null) {
            return null;
        }

        $scopes = isset($matches[2]) ? array_values(array_filter(explode(',', $matches[2]))) : RoleScopes::for($member->role ?? Role::Owner);

        return new Actor(memberId: $member->id, surface: Surface::Rest, scopes: $scopes, accountId: $member->account_id);
    }
}
