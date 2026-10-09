<?php

declare(strict_types=1);

namespace Longhand\Identity\Enums;

/**
 * A human member's role (RFC 0003). Agents have no role; their rights
 * come from their scopes, bounded by their owner's role.
 */
enum Role: string
{
    case Owner = 'owner';
    case Admin = 'admin';
    case Member = 'member';
    case Guest = 'guest';
}
