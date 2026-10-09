<?php

declare(strict_types=1);

namespace Longhand\Identity\Features\JoinWorkspace;

use Longhand\Identity\Enums\MemberKind;
use Longhand\Identity\Enums\MemberStatus;
use Longhand\Identity\Enums\Role;
use Longhand\Identity\Exceptions\AlreadyAMember;
use Longhand\Identity\Exceptions\HandleTaken;
use Longhand\Identity\Exceptions\UnverifiedAccount;
use Longhand\Identity\Models\Account;
use Longhand\Identity\Models\Member;

trait AddsMembers
{
    private function addMember(Account $account, string $workspaceId, Role $role, NewMember $member): Member
    {
        if (! $account->isVerified()) {
            throw new UnverifiedAccount;
        }

        if (Member::query()->where('workspace_id', $workspaceId)->where('account_id', $account->id)->exists()) {
            throw new AlreadyAMember;
        }

        if (Member::query()->where('workspace_id', $workspaceId)->where('handle', $member->handle->value)->exists()) {
            throw new HandleTaken($member->handle->value);
        }

        return Member::query()->create([
            'workspace_id' => $workspaceId,
            'account_id' => $account->id,
            'kind' => MemberKind::Human,
            'display_name' => $member->displayName,
            'handle' => $member->handle->value,
            'role' => $role,
            'status' => MemberStatus::Active,
            'timezone' => $member->timezone,
        ]);
    }
}
