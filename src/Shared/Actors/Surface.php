<?php

declare(strict_types=1);

namespace Longhand\Shared\Actors;

/**
 * The surface a call arrived through, recorded in the audit log.
 */
enum Surface: string
{
    case Rest = 'rest';
    case Mcp = 'mcp';
    case Web = 'web';
    case System = 'system';
}
