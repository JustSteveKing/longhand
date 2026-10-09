<?php

declare(strict_types=1);

namespace Longhand\Identity\Authorisation;

use Longhand\Identity\Enums\Role;

/**
 * The scopes each role carries (RFC 0003).
 *
 * A web session carries every scope its member's role allows. A token's
 * scopes are intersected with them, and an agent's are bounded by its
 * owner's role. Some scopes can never belong to an agent at all.
 */
final class RoleScopes
{
    /** Scopes an agent can never hold, whatever its owner's role (ADR 0016). */
    public const array HUMAN_ONLY = [
        'members:write',
        'workspace:write',
        'webhooks:write',
        'audit:read',
    ];

    private const array READ = [
        'workspace:read',
        'members:read',
        'spaces:read',
        'threads:read',
        'inbox:read',
        'briefs:read',
        'check_ins:read',
    ];

    private const array WORK = [
        'threads:write',
        'posts:write:draft',
        'posts:write',
        'requests:write',
        'requests:assign',
        'decisions:write',
        'inbox:write',
        'briefs:write',
        'check_ins:write',
        'availability:write',
    ];

    /**
     * @return list<string>
     */
    public static function for(Role $role): array
    {
        return match ($role) {
            Role::Owner, Role::Admin => [...self::READ, ...self::WORK, 'spaces:write', ...self::HUMAN_ONLY],
            // Members create spaces, webhook subscriptions and agents of their own (RFC 0003,
            // RFC 0004, RFC 0010). members:write lets them manage their own agents; the
            // guards on inviting, roles and deactivation keep the rest to owners and admins.
            Role::Member => [...self::READ, ...self::WORK, 'spaces:write', 'webhooks:write', 'members:write'],
            // Guests work inside the spaces they were added to; they create no spaces and wire up nothing.
            Role::Guest => [...self::READ, ...self::WORK],
        };
    }

    public static function allows(Role $role, string $scope): bool
    {
        return in_array($scope, self::for($role), true);
    }
}
