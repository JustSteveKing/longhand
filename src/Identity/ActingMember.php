<?php

declare(strict_types=1);

namespace Longhand\Identity;

use LogicException;
use Longhand\Identity\Models\Account;
use Longhand\Identity\Models\Member;
use Longhand\Shared\Actors\AuthorisedActor;

/**
 * Loads who an authorised actor is, for Actions and their guards.
 */
final class ActingMember
{
    public static function of(AuthorisedActor $actor): Member
    {
        $id = $actor->memberId() ?? throw new LogicException('This action needs a member.');

        return Member::query()->findOrFail($id);
    }

    public static function account(AuthorisedActor $actor): Account
    {
        $id = $actor->actor->accountId ?? throw new LogicException('This action needs an account.');

        return Account::query()->findOrFail($id);
    }
}
