<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Longhand\Identity\Enums\Role;
use Longhand\Identity\Exceptions\EmailDomainNotAllowed;
use Longhand\Identity\Exceptions\UnverifiedAccount;
use Longhand\Identity\Features\AddEmailDomain\AddEmailDomain;
use Longhand\Identity\Features\AddEmailDomain\EmailDomainPayload;
use Longhand\Identity\Features\JoinByEmailDomain\JoinByEmailDomain;
use Longhand\Identity\Features\JoinByEmailDomain\JoinByEmailDomainPayload;
use Longhand\Identity\Features\JoinWorkspace\NewMember;
use Longhand\Identity\Features\RemoveEmailDomain\RemoveEmailDomain;
use Longhand\Identity\Handle;
use Longhand\Identity\Models\Member;
use Longhand\Shared\Actions\ActionRefused;

function ownerAt(string $email): Member
{
    return Member::factory()->owner()->create(['account_id' => User::factory()->create(['email' => $email])->id]);
}

function joinByDomain(User $account, string $workspaceId): Member
{
    return runAction(accountActor($account), JoinByEmailDomain::class, new JoinByEmailDomainPayload(
        $workspaceId,
        new NewMember('Sam Lee', Handle::from('sam'), 'Europe/London'),
    ));
}

it('lets an owner with a verified address at a domain add it', function (): void {
    $owner = ownerAt('steve@acme.example');

    $domain = runAction(memberActor($owner), AddEmailDomain::class, new EmailDomainPayload(' ACME.example '));

    expect($domain->domain)->toBe('acme.example')
        ->and($domain->workspace_id)->toBe($owner->workspace_id);
});

it('refuses a domain the owner has no address at', function (): void {
    expect(fn () => runAction(memberActor(ownerAt('steve@acme.example')), AddEmailDomain::class, new EmailDomainPayload('globex.example')))
        ->toThrow(EmailDomainNotAllowed::class, 'your own verified address');
});

it('refuses public mail providers', function (): void {
    expect(fn () => runAction(memberActor(ownerAt('steve@gmail.com')), AddEmailDomain::class, new EmailDomainPayload('gmail.com')))
        ->toThrow(EmailDomainNotAllowed::class, 'public mail provider');
});

it('is for owners only', function (): void {
    $admin = Member::factory()->admin()->create(['account_id' => User::factory()->create(['email' => 'ada@acme.example'])->id]);

    expect(fn () => runAction(memberActor($admin), AddEmailDomain::class, new EmailDomainPayload('acme.example')))
        ->toThrow(ActionRefused::class, 'Only owners');
});

it('lets a verified address at the domain join as a member, never higher', function (): void {
    $owner = ownerAt('steve@acme.example');
    runAction(memberActor($owner), AddEmailDomain::class, new EmailDomainPayload('acme.example'));

    $sam = joinByDomain(User::factory()->create(['email' => 'sam@acme.example']), $owner->workspace_id);

    expect($sam->role)->toBe(Role::Member)
        ->and($sam->workspace_id)->toBe($owner->workspace_id);
});

it('does not show the workspace to anyone else', function (): void {
    $owner = ownerAt('steve@acme.example');
    runAction(memberActor($owner), AddEmailDomain::class, new EmailDomainPayload('acme.example'));

    expect(fn () => joinByDomain(User::factory()->create(['email' => 'sam@globex.example']), $owner->workspace_id))
        ->toThrow(ModelNotFoundException::class);
});

it('needs the address to be verified', function (): void {
    $owner = ownerAt('steve@acme.example');
    runAction(memberActor($owner), AddEmailDomain::class, new EmailDomainPayload('acme.example'));

    expect(fn () => joinByDomain(User::factory()->unverified()->create(['email' => 'sam@acme.example']), $owner->workspace_id))
        ->toThrow(UnverifiedAccount::class);
});

it('stops new joins when the domain is removed, and keeps existing members', function (): void {
    $owner = ownerAt('steve@acme.example');
    runAction(memberActor($owner), AddEmailDomain::class, new EmailDomainPayload('acme.example'));
    $sam = joinByDomain(User::factory()->create(['email' => 'sam@acme.example']), $owner->workspace_id);

    runAction(memberActor($owner), RemoveEmailDomain::class, new EmailDomainPayload('acme.example'));

    expect($sam->fresh()->exists)->toBeTrue();
    expect(fn () => joinByDomain(User::factory()->create(['email' => 'kim@acme.example']), $owner->workspace_id))
        ->toThrow(ModelNotFoundException::class);
});
