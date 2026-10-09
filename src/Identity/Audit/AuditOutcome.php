<?php

declare(strict_types=1);

namespace Longhand\Identity\Audit;

enum AuditOutcome: string
{
    case Allowed = 'allowed';
    case Refused = 'refused';
}
