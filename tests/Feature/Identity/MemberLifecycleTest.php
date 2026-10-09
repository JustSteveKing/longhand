<?php

declare(strict_types=1);

use App\Models\User;
use Longhand\Identity\Enums\MemberKind;
use Longhand\Identity\Enums\MemberStatus;
use Longhand\Identity\Enums\Role;
use Longhand\Identity\Events\AgentSuspended;
use Longhand\Identity\Events\MemberDeactivated;
use Longhand\Identity\Exceptions\HandleTaken;
use Longhand\Identity\Exceptions\LastOwner;
use Longhand\Identity\Features\ChangeRole\ChangeRole;
use Longhand\Identity\Features\ChangeRole\ChangeRolePayload;
use Longhand\Identity\Features\ChangeRole\MemberPayload;
use Longhand\Identity\Features\DeactivateMember\DeactivateMember;
use Longhand\Identity\Features\DeleteAccount\DeleteAccount;
use Longhand\Identity\Features\LeaveWorkspace\LeaveWorkspace;
use Longhand\Identity\Features\ReactivateMember\ReactivateMember;
use Longhand\Identity\Features\UpdateProfile\UpdateProfile;
use Longhand\Identity\Features\UpdateProfile\UpdateProfilePayload;
use Longhand\Identity\Handle;
use Longhand\Identity\Models\Member;
use Longhand\Shared\Actions\ActionLog;
use Longhand\Shared\Actions\ActionRefused;
use Longhand\Shared\Actions\NoInput;
use Longhand\Shared\Errors\InvalidTransition;
use Tests\Support\RecordingActionLog;

function agentOwnedBy(Member $owner): Member
{
    return Member::factory()->create([
        'workspace_id' => $owner->workspace_id,
        'account_id' => null,
        'kind' => MemberKind::Agent,
        'role' => null,
        'owner_id' => $owner->id,
    ]);
}

beforeEach(function (): void {
    $this->owner = Member::factory()->owner()->create();
    $this->admin = Member::factory()->admin()->create(['workspace_id' => $this->owner->workspace_id]);
    $this->priya = Member::factory()->create(['workspace_id' => $this->owner->workspace_id]);
});

it('lets admins change roles, but only owners make owners', function (): void {
    runAction(memberActor($this->admin), ChangeRole::class, new ChangeRolePayload($this->priya->id, Role::Guest));
    expect($this->priya->fresh()->role)->toBe(Role::Guest);

    expect(fn () => runAction(memberActor($this->admin), ChangeRole::class, new ChangeRolePayload($this->priya->id, Role::Owner)))
        ->toThrow(ActionRefused::class, 'Only owners');

    runAction(memberActor($this->owner), ChangeRole::class, new ChangeRolePayload($this->priya->id, Role::Owner));
    expect($this->priya->fresh()->role)->toBe(Role::Owner);
});

it('never demotes the last owner', function (): void {
    expect(fn () => runAction(memberActor($this->owner), ChangeRole::class, new ChangeRolePayload($this->owner->id, Role::Admin)))
        ->toThrow(fn (LastOwner $error) => expect($error->meta())->toBe(['workspaces' => [$this->owner->workspace_id]]));
});

it('lets a person edit their own profile without a scope', function (): void {
    runAction(memberActor($this->priya, scopes: []), UpdateProfile::class, new UpdateProfilePayload(displayName: 'Priya P', timezone: 'Asia/Kolkata'));

    expect($this->priya->fresh())->display_name->toBe('Priya P')->timezone->toBe('Asia/Kolkata');
    expect(fn () => runAction(memberActor($this->priya), UpdateProfile::class, new UpdateProfilePayload(handle: Handle::from($this->admin->handle))))
        ->toThrow(HandleTaken::class);
});

it('deactivates a member, suspending their agents, and keeps what they wrote', function (): void {
    $log = new RecordingActionLog;
    $this->app->instance(ActionLog::class, $log);
    $agent = agentOwnedBy($this->priya);

    runAction(memberActor($this->admin), DeactivateMember::class, new MemberPayload($this->priya->id));

    expect($this->priya->fresh()->status)->toBe(MemberStatus::Deactivated)
        ->and($agent->fresh()->status)->toBe(MemberStatus::Suspended)
        ->and($log->events())->toEqual([
            new AgentSuspended($agent->workspace_id, $agent->id, 'owner_deactivated'),
            new MemberDeactivated($this->priya->workspace_id, $this->priya->id, 'deactivated', [$agent->id]),
        ]);
});

it('lets only an owner deactivate an owner, and never the last one', function (): void {
    $second = Member::factory()->owner()->create(['workspace_id' => $this->owner->workspace_id]);

    expect(fn () => runAction(memberActor($this->admin), DeactivateMember::class, new MemberPayload($second->id)))
        ->toThrow(ActionRefused::class, 'Only an owner');

    runAction(memberActor($this->owner), DeactivateMember::class, new MemberPayload($second->id));
    expect($second->fresh()->status)->toBe(MemberStatus::Deactivated);
});

it('refuses members who try to deactivate someone', function (): void {
    expect(fn () => runAction(memberActor($this->priya), DeactivateMember::class, new MemberPayload($this->admin->id)))
        ->toThrow(ActionRefused::class);
});

it('lets an agent\'s owner deactivate it', function (): void {
    $agent = agentOwnedBy($this->priya);

    runAction(memberActor($this->priya), DeactivateMember::class, new MemberPayload($agent->id));

    expect($agent->fresh()->status)->toBe(MemberStatus::Deactivated);
});

it('reactivates a deactivated person', function (): void {
    runAction(memberActor($this->admin), DeactivateMember::class, new MemberPayload($this->priya->id));
    runAction(memberActor($this->admin), ReactivateMember::class, new MemberPayload($this->priya->id));

    expect($this->priya->fresh()->status)->toBe(MemberStatus::Active);
    expect(fn () => runAction(memberActor($this->admin), ReactivateMember::class, new MemberPayload($this->priya->id)))
        ->toThrow(InvalidTransition::class);
});

it('lets a person leave, but not the last owner', function (): void {
    runAction(memberActor($this->priya, scopes: []), LeaveWorkspace::class, new NoInput);
    expect($this->priya->fresh()->status)->toBe(MemberStatus::Deactivated);

    expect(fn () => runAction(memberActor($this->owner), LeaveWorkspace::class, new NoInput))->toThrow(LastOwner::class);
});

it('does not let an agent leave', function (): void {
    $agent = agentOwnedBy($this->priya);

    expect(fn () => runAction(memberActor($agent, scopes: []), LeaveWorkspace::class, new NoInput))
        ->toThrow(ActionRefused::class, 'Agents cannot');
});

it('deletes an account: every membership ends, agents are suspended, the login is gone', function (): void {
    $account = User::query()->findOrFail($this->priya->account_id);
    $elsewhere = Member::factory()->create(['account_id' => $account->id]);
    $agent = agentOwnedBy($this->priya);

    runAction(accountActor($account), DeleteAccount::class, new NoInput);

    expect(User::query()->find($account->id))->toBeNull()
        ->and($this->priya->fresh())->status->toBe(MemberStatus::Deactivated)->account_id->toBeNull()
        ->and($elsewhere->fresh()->status)->toBe(MemberStatus::Deactivated)
        ->and($agent->fresh()->status)->toBe(MemberStatus::Suspended)
        ->and($this->priya->fresh()->display_name)->toBe($this->priya->display_name);

    User::factory()->create(['email' => $account->email]);
});

it('refuses to delete the account of a workspace\'s last owner, naming the workspaces', function (): void {
    $account = User::query()->findOrFail($this->owner->account_id);

    expect(fn () => runAction(accountActor($account), DeleteAccount::class, new NoInput))
        ->toThrow(fn (LastOwner $error) => expect($error->workspaceIds)->toBe([$this->owner->workspace_id]));
    expect(User::query()->find($account->id))->not->toBeNull();
});

it('does not bring back a person whose account was deleted', function (): void {
    runAction(accountActor(User::query()->findOrFail($this->priya->account_id)), DeleteAccount::class, new NoInput);

    expect(fn () => runAction(memberActor($this->admin), ReactivateMember::class, new MemberPayload($this->priya->id)))
        ->toThrow(InvalidTransition::class, 'deleted their account');
});
