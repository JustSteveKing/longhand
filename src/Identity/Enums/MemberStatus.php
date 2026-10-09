<?php

declare(strict_types=1);

namespace Longhand\Identity\Enums;

/**
 * Only agents are ever suspended (RFC 0003).
 */
enum MemberStatus: string
{
    case Active = 'active';
    case Deactivated = 'deactivated';
    case Suspended = 'suspended';
}
