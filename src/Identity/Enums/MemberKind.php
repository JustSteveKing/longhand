<?php

declare(strict_types=1);

namespace Longhand\Identity\Enums;

enum MemberKind: string
{
    case Human = 'human';
    case Agent = 'agent';
}
