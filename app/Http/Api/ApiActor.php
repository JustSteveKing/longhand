<?php

declare(strict_types=1);

namespace App\Http\Api;

use Illuminate\Http\Request;
use LogicException;
use Longhand\Shared\Actors\Actor;

final class ApiActor
{
    public static function of(Request $request): Actor
    {
        $actor = $request->attributes->get('actor');

        return $actor instanceof Actor ? $actor : throw new LogicException('No actor on this request.');
    }
}
