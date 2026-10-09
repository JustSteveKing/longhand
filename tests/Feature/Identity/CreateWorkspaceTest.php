<?php

declare(strict_types=1);

use App\Models\User;
use Longhand\Identity\Audit\AuditEvent;
use Longhand\Identity\Enums\MemberKind;
use Longhand\Identity\Enums\MemberStatus;
use Longhand\Identity\Enums\Role;
use Longhand\Identity\Events\MemberJoined;
use Longhand\Identity\Events\WorkspaceCreated;
use Longhand\Identity\Exceptions\HandleTaken;
use Longhand\Identity\Exceptions\InvalidHandle;
use Longhand\Identity\Features\CreateWorkspace\CreateWorkspace;
use Longhand\Identity\Features\CreateWorkspace\CreateWorkspacePayload;
use Longhand\Identity\Handle;
use Longhand\Identity\Models\Workspace;
use Longhand\Shared\Actions\ActionLog;
use Longhand\Shared\Actions\ActionRunner;
use Longhand\Shared\Actors\Actor;
use Longhand\Shared\Actors\Surface;
use Tests\Support\RecordingActionLog;

beforeEach(function (): void {
    $this->log = new RecordingActionLog;
    $this->app->instance(ActionLog::class, $this->log);
});

function createWorkspace(User $account, string $handle = 'acme'): mixed
{
    return app(ActionRunner::class)->run(
        new Actor(memberId: null, surface: Surface::Web, accountId: $account->id),
        CreateWorkspace::class,
        new CreateWorkspacePayload(
            name: 'Acme',
            handle: Handle::from($handle),
            displayName: 'Steve McDougall',
            memberHandle: Handle::from('steve'),
            timezone: 'Europe/London',
        ),
    );
}

it('creates a workspace with the account as its owner', function (): void {
    $account = User::factory()->create();

    $owner = createWorkspace($account);

    expect($owner->id)->toMatch('/^mem_[0-9A-HJKMNP-TV-Z]{26}$/')
        ->and($owner->workspace->id)->toMatch('/^wsp_[0-9A-HJKMNP-TV-Z]{26}$/')
        ->and($owner->workspace->handle)->toBe('acme')
        ->and($owner->account_id)->toBe($account->id)
        ->and($owner->kind)->toBe(MemberKind::Human)
        ->and($owner->role)->toBe(Role::Owner)
        ->and($owner->status)->toBe(MemberStatus::Active);
});

it('starts a workspace with the RFC 0003 defaults', function (): void {
    $workspace = createWorkspace(User::factory()->create())->workspace->fresh();

    expect($workspace->default_timezone)->toBe('Europe/London')
        ->and($workspace->max_agents_per_member)->toBe(5)
        ->and($workspace->max_assistants_per_member)->toBe(5)
        ->and($workspace->brief_daily_limit)->toBe(30)
        ->and($workspace->semantic_search)->toBeTrue()
        ->and($workspace->subscription_approval)->toBeTrue();
});

it('records that the workspace was created and its owner joined', function (): void {
    $owner = createWorkspace(User::factory()->create());

    $events = $this->log->events();

    expect($events)->toHaveCount(2)
        ->and($events[0])->toEqual(new WorkspaceCreated($owner->workspace_id, $owner->id))
        ->and($events[1])->toEqual(new MemberJoined($owner->workspace_id, $owner->id));
});

it('audits the creation against the new workspace', function (): void {
    $this->app->forgetInstance(ActionLog::class);
    $this->app->forgetScopedInstances();
    $owner = createWorkspace(User::factory()->create());

    expect(AuditEvent::query()->sole())
        ->action->toBe('workspace.create')
        ->workspace_id->toBe($owner->workspace_id)
        ->subject_id->toBe($owner->id);
});

it('refuses a handle another workspace has, and creates nothing', function (): void {
    createWorkspace(User::factory()->create(), 'acme');

    expect(fn () => createWorkspace(User::factory()->create(), 'acme'))->toThrow(HandleTaken::class);
    expect(Workspace::query()->count())->toBe(1);
});

it('lets one account own several workspaces', function (): void {
    $account = User::factory()->create();

    createWorkspace($account, 'acme');
    createWorkspace($account, 'globex');

    expect(Workspace::query()->count())->toBe(2);
});

it('only accepts handles of lowercase letters, digits and hyphens', function (string $handle): void {
    expect(fn () => Handle::from($handle))->toThrow(InvalidHandle::class);
})->with(['A', 'Acme', 'acme corp', 'acme_corp', str_repeat('a', 33), 'ac!']);
