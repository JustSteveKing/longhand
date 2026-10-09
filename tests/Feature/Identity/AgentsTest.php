<?php

declare(strict_types=1);

use Longhand\Identity\Enums\MemberKind;
use Longhand\Identity\Enums\MemberStatus;
use Longhand\Identity\Events\AgentOwnerChanged;
use Longhand\Identity\Exceptions\AgentLimitReached;
use Longhand\Identity\Exceptions\InvalidApprovalRule;
use Longhand\Identity\Exceptions\ScopeExceedsOwner;
use Longhand\Identity\Features\ConfigureAgent\AgentPayload;
use Longhand\Identity\Features\ConfigureAgent\ConfigureAgent;
use Longhand\Identity\Features\ConfigureAgent\ConfigureAgentPayload;
use Longhand\Identity\Features\CreateAgent\CreateAgent;
use Longhand\Identity\Features\CreateAgent\CreateAgentPayload;
use Longhand\Identity\Features\DelegateAgent\DelegateAgent;
use Longhand\Identity\Features\DelegateAgent\DelegateAgentPayload;
use Longhand\Identity\Features\ResumeAgent\ResumeAgent;
use Longhand\Identity\Features\SuspendAgent\SuspendAgent;
use Longhand\Identity\Features\TransferAgent\TransferAgent;
use Longhand\Identity\Features\TransferAgent\TransferAgentPayload;
use Longhand\Identity\Handle;
use Longhand\Identity\Models\Member;
use Longhand\Shared\Actions\Action;
use Longhand\Shared\Actions\ActionLog;
use Longhand\Shared\Actions\ActionRefused;
use Longhand\Shared\Actions\Approvable;
use Longhand\Shared\Actions\NoInput;
use Longhand\Shared\Actors\AuthorisedActor;
use Longhand\Shared\Errors\InvalidTransition;
use Tests\Support\RecordingActionLog;

function createAgent(Member $by, array $scopes = ['threads:read', 'posts:write:draft'], ?string $ownerId = null, string $handle = 'triage', array $rules = []): Member
{
    return runAction(memberActor($by), CreateAgent::class, new CreateAgentPayload(
        displayName: 'Triage',
        handle: Handle::from($handle),
        scopes: $scopes,
        requiresApprovalFor: $rules,
        description: 'Routes new support threads',
        ownerId: $ownerId,
    ))->agent;
}

#[Action('post.publish', scope: 'posts:write')]
final readonly class PublishSomething implements Approvable
{
    public function handle(AuthorisedActor $actor, NoInput $payload): string
    {
        return 'published';
    }

    public function draft(AuthorisedActor $actor, object $payload): string
    {
        return 'drafted';
    }
}

beforeEach(function (): void {
    $this->steve = Member::factory()->create();
});

it('lets a member create an agent they own', function (): void {
    $agent = createAgent($this->steve);

    expect($agent->kind)->toBe(MemberKind::Agent)
        ->and($agent->owner_id)->toBe($this->steve->id)
        ->and($agent->role)->toBeNull()
        ->and($agent->account_id)->toBeNull()
        ->and($agent->status)->toBe(MemberStatus::Active)
        ->and($agent->scopes)->toBe(['threads:read', 'posts:write:draft'])
        ->and($agent->timezone)->toBe($this->steve->timezone);
});

it('never gives an agent a scope its owner cannot grant', function (): void {
    expect(fn () => createAgent($this->steve, ['threads:read', 'made:up']))
        ->toThrow(fn (ScopeExceedsOwner $error) => expect($error->scopes)->toBe(['made:up']));
});

it('never gives an agent a human-only scope, even an owner\'s', function (array $scopes): void {
    $owner = Member::factory()->owner()->create();

    expect(fn () => createAgent($owner, $scopes))->toThrow(ScopeExceedsOwner::class);
})->with([[['members:write']], [['workspace:write']], [['webhooks:write']], [['audit:read']]]);

it('refuses guests an agent of their own', function (): void {
    $guest = Member::factory()->guest()->create();

    expect(fn () => createAgent($guest, []))->toThrow(ActionRefused::class, 'members:write');
});

it('lets admins create agents for other people, and no one else', function (): void {
    $admin = Member::factory()->admin()->create(['workspace_id' => $this->steve->workspace_id]);
    $other = Member::factory()->create(['workspace_id' => $this->steve->workspace_id]);

    expect(createAgent($admin, ownerId: $this->steve->id)->owner_id)->toBe($this->steve->id);
    expect(fn () => createAgent($other, ownerId: $this->steve->id, handle: 'other'))->toThrow(ActionRefused::class, 'Only owners and admins');
});

it('caps agents per owner, 5 by default', function (): void {
    foreach (range(1, 5) as $n) {
        createAgent($this->steve, handle: "agent-{$n}");
    }

    expect(fn () => createAgent($this->steve, handle: 'agent-6'))
        ->toThrow(fn (AgentLimitReached $error) => expect($error->meta())->toBe(['limit' => 5, 'owned' => 5, 'kind' => 'agent']));
});

it('refuses approval rules that are not action names', function (): void {
    expect(fn () => createAgent($this->steve, rules: ['posts:write']))->toThrow(InvalidApprovalRule::class);
});

it('takes the draft path for an action in the agent\'s approval rules', function (): void {
    $owner = Member::factory()->owner()->create();
    $agent = createAgent($owner, ['posts:write'], rules: ['post.publish']);

    expect(runAction(memberActor($agent, ['posts:write']), PublishSomething::class, new NoInput))->toBe('drafted');
});

it('lets the owner change the agent\'s scopes, within the owner\'s role', function (): void {
    $agent = createAgent($this->steve);

    $changed = runAction(memberActor($this->steve), ConfigureAgent::class, new ConfigureAgentPayload($agent->id, scopes: ['threads:read']));

    expect($changed->scopes)->toBe(['threads:read']);
    expect(fn () => runAction(memberActor($this->steve), ConfigureAgent::class, new ConfigureAgentPayload($agent->id, scopes: ['audit:read'])))
        ->toThrow(ScopeExceedsOwner::class);
});

it('never lets an agent change itself', function (): void {
    $agent = createAgent($this->steve, ['members:read']);

    expect(fn () => runAction(memberActor($agent, ['members:write']), ConfigureAgent::class, new ConfigureAgentPayload($agent->id, scopes: [])))
        ->toThrow(ActionRefused::class, 'Agents cannot');
});

it('does not let another member change someone else\'s agent', function (): void {
    $agent = createAgent($this->steve);
    $other = Member::factory()->create(['workspace_id' => $this->steve->workspace_id]);

    expect(fn () => runAction(memberActor($other), ConfigureAgent::class, new ConfigureAgentPayload($agent->id, scopes: [])))
        ->toThrow(ActionRefused::class);
});

it('transfers an agent, dropping what the new owner cannot grant and ending delegation', function (): void {
    $log = new RecordingActionLog;
    $this->app->instance(ActionLog::class, $log);
    $admin = Member::factory()->admin()->create(['workspace_id' => $this->steve->workspace_id]);
    $guest = Member::factory()->guest()->create(['workspace_id' => $this->steve->workspace_id]);
    $priya = Member::factory()->create(['workspace_id' => $this->steve->workspace_id]);
    $agent = createAgent($this->steve, ['threads:read', 'spaces:write']);
    runAction(memberActor($this->steve), DelegateAgent::class, new DelegateAgentPayload($agent->id, actForMe: true));

    expect(fn () => runAction(memberActor($admin), TransferAgent::class, new TransferAgentPayload($agent->id, $guest->id)))
        ->toThrow(InvalidTransition::class);

    $moved = runAction(memberActor($admin), TransferAgent::class, new TransferAgentPayload($agent->id, $priya->id));

    expect($moved->owner_id)->toBe($priya->id)
        ->and($moved->acts_on_behalf_of_id)->toBeNull()
        ->and(end($log->allowed)['events'][0])->toBeInstanceOf(AgentOwnerChanged::class);
});

it('lets only the owner have the agent act for them', function (): void {
    $agent = createAgent($this->steve);
    $admin = Member::factory()->admin()->create(['workspace_id' => $this->steve->workspace_id]);

    expect(runAction(memberActor($this->steve), DelegateAgent::class, new DelegateAgentPayload($agent->id, true))->acts_on_behalf_of_id)
        ->toBe($this->steve->id);
    expect(fn () => runAction(memberActor($admin), DelegateAgent::class, new DelegateAgentPayload($agent->id, true)))
        ->toThrow(ActionRefused::class);
});

it('suspends and resumes an agent, and only resumes it while its owner is active', function (): void {
    $agent = createAgent($this->steve);

    runAction(memberActor($this->steve), SuspendAgent::class, new AgentPayload($agent->id));
    expect($agent->fresh()->status)->toBe(MemberStatus::Suspended);
    expect(fn () => runAction(memberActor($this->steve), SuspendAgent::class, new AgentPayload($agent->id)))
        ->toThrow(InvalidTransition::class);

    $admin = Member::factory()->admin()->create(['workspace_id' => $this->steve->workspace_id]);
    $this->steve->update(['status' => MemberStatus::Deactivated]);
    expect(fn () => runAction(memberActor($admin), ResumeAgent::class, new AgentPayload($agent->id)))
        ->toThrow(InvalidTransition::class);

    $this->steve->update(['status' => MemberStatus::Active]);
    runAction(memberActor($admin), ResumeAgent::class, new AgentPayload($agent->id));
    expect($agent->fresh()->status)->toBe(MemberStatus::Active);
});

it('does not let a suspended agent act', function (): void {
    $agent = createAgent($this->steve, ['posts:write']);
    runAction(memberActor($this->steve), SuspendAgent::class, new AgentPayload($agent->id));

    expect(fn () => runAction(memberActor($agent, ['posts:write']), PublishSomething::class, new NoInput))
        ->toThrow(ActionRefused::class, 'not active');
});
