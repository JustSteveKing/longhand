<?php

declare(strict_types=1);

namespace App\Http\Api;

use Illuminate\Http\Request;
use Longhand\Identity\Models\Member;

final class CurrentMember
{
    public static function of(Request $request): Member
    {
        return Member::query()->findOrFail(ApiActor::of($request)->memberId);
    }
}
