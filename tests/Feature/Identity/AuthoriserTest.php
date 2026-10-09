<?php

declare(strict_types=1);

use App\Models\User;
use Longhand\Identity\Audit\AuditEvent;
use Longhand\Identity\Audit\AuditOutcome;
use Longhand\Identity\Authorisation\RoleScopes;
use Longhand\Identity\Enums\MemberKind;
use Longhand\Identity\Enums\MemberStatus;
use Longhand\Identity\Enums\Role;
use Longhand\Identity\Models\Member;
use Longhand\Shared\Actions\Action;
use Longhand\Shared\Actions\ActionRefused;
use Longhand\Shared\Actions\ActionRunner;
use Longhand\Shared\Actions\Authorisation;
use Longhand\Shared\Actions\Guarded;
use Longhand\Shared\Actors\Actor;
use Longhand\Shared\Actors\AuthorisedActor;
use Longhand\Shared\Actors\Surface;

final readonly class NoPayload {}

#[Action('space.create', scope: 'spaces:write')]
final readonly class CreateSomething
{
    public function handle(AuthorisedActor $actor, NoPayload $payload): string
    {
        return 'done';
    }
}

#[Action('member.invite', scope: 'members:write', humansOnly: true)]
final readonly class InviteSomeone
{
    public function handle(AuthorisedActor $actor, NoPayload $payload): string
    {
        return 'invited';
    }
}

#[Action('member.update_profile', humansOnly: true)]
final readonly class UpdateOwnProfile
{
    public function handle(AuthorisedActor $actor, NoPayload $payload): string
    {
        return 'updated';
    }
}

#[Action('thread.resolve', scope: 'threads:write')]
final readonly class ResolveIfOwner implements Guarded
{
    public function guard(AuthorisedActor $actor, object $payload): Authorisation
    {
        return Authorisation::refuse('Only the thread owner can resolve it.');
    }

    public function handle(AuthorisedActor $actor, NoPayload $payload): string
    {
        return 'resolved';
    }
}

function actingAs(Member $member, ?array $scopes = null, Surface $surface = Surface::Rest): Actor
{
    return new Actor(
        memberId: $member->id,
        surface: $surface,
        scopes: $scopes ?? RoleScopes::for($member->role ?? Role::Owner),
        accountId: $member->account_id,
    );
}

function run(Actor $actor, string $action): mixed
{
    return app(ActionRunner::class)->run($actor, $action, new NoPayload);
}

function agentOf(Member $owner, MemberStatus $status = MemberStatus::Active): Member
{
    return Member::factory()->create([
        'workspace_id' => $owner->workspace_id,
        'account_id' => null,
        'kind' => MemberKind::Agent,
        'role' => null,
        'status' => $status,
        'owner_id' => $owner->id,
    ]);
}

it('allows a member whose role and token carry the scope', function (): void {
    expect(run(actingAs(Member::factory()->create()), CreateSomething::class))->toBe('done');
});

it('refuses a token without the scope, even when the role has it', function (): void {
    expect(fn () => run(actingAs(Member::factory()->create(), scopes: ['threads:read']), CreateSomething::class))
        ->toThrow(ActionRefused::class, 'spaces:write');
});

it('refuses a scope the role does not carry, even when the token claims it', function (): void {
    $guest = Member::factory()->guest()->create();

    expect(fn () => run(actingAs($guest, scopes: ['spaces:write']), CreateSomething::class))
        ->toThrow(ActionRefused::class, 'role');
});

it('refuses a deactivated member', function (): void {
    $member = Member::factory()->create(['status' => MemberStatus::Deactivated]);

    expect(fn () => run(actingAs($member), CreateSomething::class))->toThrow(ActionRefused::class, 'not active');
});

it('refuses an account with no member, except for account-level actions', function (): void {
    $actor = new Actor(memberId: null, surface: Surface::Web, accountId: User::factory()->create()->id);

    expect(fn () => run($actor, CreateSomething::class))->toThrow(ActionRefused::class, 'needs a member');
});

it('lets scheduled work run as the system', function (): void {
    expect(run(Actor::system(), CreateSomething::class))->toBe('done');
});

it('lets an agent act within its scopes and its owner\'s role', function (): void {
    $agent = agentOf(Member::factory()->create());

    expect(run(actingAs($agent, scopes: ['spaces:write']), CreateSomething::class))->toBe('done');
});

it('never lets an agent exceed its owner', function (): void {
    $agent = agentOf(Member::factory()->guest()->create());

    expect(fn () => run(actingAs($agent, scopes: ['spaces:write']), CreateSomething::class))
        ->toThrow(ActionRefused::class, "agent's owner");
});

it('never lets an agent manage identity, whatever its scopes', function (string $action): void {
    $agent = agentOf(Member::factory()->owner()->create());

    expect(fn () => run(actingAs($agent, scopes: ['members:write']), $action))
        ->toThrow(ActionRefused::class, 'Agents cannot');
})->with([InviteSomeone::class, UpdateOwnProfile::class]);

it('refuses an agent whose owner is gone', function (): void {
    $owner = Member::factory()->create();
    $agent = agentOf($owner);
    $owner->update(['status' => MemberStatus::Deactivated]);

    expect(fn () => run(actingAs($agent, scopes: ['spaces:write']), CreateSomething::class))
        ->toThrow(ActionRefused::class, 'no active owner');
});

it('lets an Action refuse on rules of its own', function (): void {
    expect(fn () => run(actingAs(Member::factory()->create()), ResolveIfOwner::class))
        ->toThrow(ActionRefused::class, 'Only the thread owner');
});

it('audits an allowed action with its surface, scope and actor', function (): void {
    $member = Member::factory()->create();

    run(actingAs($member, surface: Surface::Mcp), CreateSomething::class);

    $entry = AuditEvent::query()->sole();

    expect($entry->id)->toMatch('/^aud_/')
        ->and($entry->workspace_id)->toBe($member->workspace_id)
        ->and($entry->action)->toBe('space.create')
        ->and($entry->outcome)->toBe(AuditOutcome::Allowed)
        ->and($entry->surface)->toBe(Surface::Mcp)
        ->and($entry->scope)->toBe('spaces:write')
        ->and($entry->actor_id)->toBe($member->id)
        ->and($entry->reason)->toBeNull();
});

it('audits a refusal on its own, with the reason and nothing it attempted', function (): void {
    $guest = Member::factory()->guest()->create();

    try {
        run(actingAs($guest, scopes: ['spaces:write']), CreateSomething::class);
    } catch (ActionRefused) {
    }

    $entry = AuditEvent::query()->sole();

    expect($entry->outcome)->toBe(AuditOutcome::Refused)
        ->and($entry->workspace_id)->toBe($guest->workspace_id)
        ->and($entry->reason)->toContain('role')
        ->and($entry->subject_id)->toBeNull()
        ->and($entry->changes)->toBeNull();
});

it('never changes or deletes an audit entry', function (): void {
    run(actingAs(Member::factory()->create()), CreateSomething::class);
    $entry = AuditEvent::query()->sole();

    expect(fn () => $entry->update(['action' => 'something.else']))->toThrow(LogicException::class)
        ->and(fn () => $entry->delete())->toThrow(LogicException::class);
});
