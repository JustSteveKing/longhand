<?php

declare(strict_types=1);

use App\Http\Api\JsonApi\Document;
use App\Http\Api\JsonApi\Versions;
use Illuminate\Http\Request;
use Illuminate\Testing\TestResponse;
use Longhand\Identity\Audit\AuditEvent;
use Longhand\Identity\Enums\MemberKind;
use Longhand\Identity\Enums\MemberStatus;
use Longhand\Identity\Enums\Role;
use Longhand\Identity\Models\Invitation;
use Longhand\Identity\Models\Member;
use Symfony\Component\Yaml\Yaml;

function etagOf(string $url, Member $as): string
{
    return (string) test()->getJson($url, asMember($as))->headers->get('ETag');
}

function createAgentOver(Member $owner, array $attributes = []): TestResponse
{
    return test()->postJson('/v1/members', ['data' => ['type' => 'members', 'attributes' => [
        'kind' => 'agent',
        'display_name' => 'Triage',
        'handle' => 'triage',
        'scopes' => ['threads:read', 'posts:write:draft'],
        'requires_approval_for' => ['post.publish'],
        ...$attributes,
    ]]], asMember($owner));
}

beforeEach(function (): void {
    $this->owner = Member::factory()->owner()->create();
    $this->workspace = $this->owner->workspace;
});

it('matches the contract\'s attributes and relationships for every Identity type', function (string $schema, Closure $model): void {
    $contract = Yaml::parseFile(base_path('api/openapi.yaml'))['components']['schemas'][$schema]['properties'];
    $resource = app(Document::class)->resourceArray($model($this), Request::create('/v1/me'));

    expect(array_keys($resource['attributes']))->toEqualCanonicalizing(array_keys($contract['attributes']['properties']))
        ->and(array_keys($resource['relationships'] ?? []))->toEqualCanonicalizing(array_keys($contract['relationships']['properties'] ?? []));
})->with([
    'Member' => ['Member', fn ($test) => $test->owner],
    'Workspace' => ['Workspace', fn ($test) => $test->workspace],
    'Invitation' => ['Invitation', fn ($test) => Invitation::query()->create([
        'workspace_id' => $test->workspace->id, 'email' => 'a@b.example', 'role' => Role::Member,
        'status' => 'pending', 'token_hash' => str_repeat('a', 64), 'invited_by_id' => $test->owner->id,
        'expires_at' => now()->addDays(7),
    ])],
    'AuditEvent' => ['AuditEvent', fn ($test) => AuditEvent::query()->create([
        'workspace_id' => $test->workspace->id, 'action' => 'member.invite', 'outcome' => 'allowed',
        'surface' => 'rest', 'occurred_at' => now(),
    ])],
]);

it('shows the caller with any token', function (): void {
    $this->getJson('/v1/me', asMember($this->owner, []))
        ->assertOk()
        ->assertJsonPath('data.attributes.role', 'owner')
        ->assertJsonPath('data.attributes.kind', 'human');
});

it('shows and changes the workspace', function (): void {
    $this->getJson('/v1/workspace', asMember($this->owner))
        ->assertOk()
        ->assertJsonPath('data.type', 'workspaces')
        ->assertJsonPath('data.attributes.max_agents_per_member', 5)
        ->assertJsonPath('data.links.self', url('/v1/workspace'));

    $this->patchJson('/v1/workspace', ['data' => ['type' => 'workspaces', 'id' => $this->workspace->id, 'attributes' => ['brief_daily_limit' => 60]]], [
        ...asMember($this->owner), 'If-Match' => etagOf('/v1/workspace', $this->owner),
    ])->assertOk()->assertJsonPath('data.attributes.brief_daily_limit', 60);
});

it('keeps the handle and semantic search to owners', function (string $attribute, mixed $value): void {
    $admin = Member::factory()->admin()->create(['workspace_id' => $this->workspace->id]);

    $this->patchJson('/v1/workspace', ['data' => ['type' => 'workspaces', 'id' => $this->workspace->id, 'attributes' => [$attribute => $value]]], [
        ...asMember($admin), 'If-Match' => etagOf('/v1/workspace', $admin),
    ])->assertForbidden()->assertJsonPath('errors.0.meta.action', 'workspace.update');
})->with([['handle', 'renamed'], ['semantic_search', false]]);

it('lists members by display name, filters by kind, and includes owners', function (): void {
    Member::factory()->create(['workspace_id' => $this->workspace->id, 'display_name' => 'Ada']);
    $agent = Member::factory()->create([
        'workspace_id' => $this->workspace->id, 'kind' => MemberKind::Agent, 'role' => null,
        'account_id' => null, 'owner_id' => $this->owner->id, 'display_name' => 'Zed',
    ]);

    $names = array_column(array_column($this->getJson('/v1/members', asMember($this->owner))->json('data'), 'attributes'), 'display_name');
    expect($names[0])->toBe('Ada');

    $this->getJson('/v1/members?filter[kind]=agent&include=owner', asMember($this->owner))
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $agent->id)
        ->assertJsonPath('data.0.relationships.owner.data.id', $this->owner->id)
        ->assertJsonPath('included.0.id', $this->owner->id);

    $this->getJson('/v1/members?filter[kind]=robot', asMember($this->owner))->assertStatus(400);
});

it('creates an agent with 201 and a Location', function (): void {
    $response = createAgentOver($this->owner)->assertCreated()
        ->assertJsonPath('data.attributes.kind', 'agent')
        ->assertJsonPath('data.attributes.scopes', ['threads:read', 'posts:write:draft'])
        ->assertJsonPath('data.attributes.requires_approval_for', ['post.publish'])
        ->assertJsonPath('data.relationships.owner.data.id', $this->owner->id)
        ->assertHeader('ETag');

    expect($response->headers->get('Location'))->toBe(url('/v1/members/'.$response->json('data.id')));
});

it('suspends and resumes an agent through PATCH of its status', function (): void {
    $agentId = createAgentOver($this->owner)->json('data.id');

    $this->patchJson('/v1/members/'.$agentId, ['data' => ['type' => 'members', 'id' => $agentId, 'attributes' => ['status' => 'suspended']]], [
        ...asMember($this->owner), 'If-Match' => etagOf('/v1/members/'.$agentId, $this->owner),
    ])->assertOk()->assertJsonPath('data.attributes.status', 'suspended');

    $this->patchJson('/v1/members/'.$agentId, ['data' => ['type' => 'members', 'id' => $agentId, 'attributes' => ['status' => 'active']]], [
        ...asMember($this->owner), 'If-Match' => etagOf('/v1/members/'.$agentId, $this->owner),
    ])->assertOk()->assertJsonPath('data.attributes.status', 'active');
});

it('applies several changes in one PATCH, or none of them', function (): void {
    $agentId = createAgentOver($this->owner)->json('data.id');
    $priya = Member::factory()->create(['workspace_id' => $this->workspace->id]);

    $this->patchJson('/v1/members/'.$agentId, ['data' => [
        'type' => 'members', 'id' => $agentId,
        'attributes' => ['description' => 'Now Priya\'s'],
        'relationships' => ['owner' => ['data' => ['type' => 'members', 'id' => $priya->id]]],
    ]], [...asMember($this->owner), 'If-Match' => etagOf('/v1/members/'.$agentId, $this->owner)])
        ->assertOk()
        ->assertJsonPath('data.attributes.description', 'Now Priya\'s')
        ->assertJsonPath('data.relationships.owner.data.id', $priya->id);

    $guest = Member::factory()->guest()->create(['workspace_id' => $this->workspace->id]);
    $this->patchJson('/v1/members/'.$agentId, ['data' => [
        'type' => 'members', 'id' => $agentId,
        'attributes' => ['description' => 'Should not stick'],
        'relationships' => ['owner' => ['data' => ['type' => 'members', 'id' => $guest->id]]],
    ]], [...asMember($this->owner), 'If-Match' => etagOf('/v1/members/'.$agentId, $this->owner)])
        ->assertStatus(409);

    expect(Member::query()->find($agentId))->description->toBe('Now Priya\'s')->owner_id->toBe($priya->id);
});

it('changes a role, and protects the last owner', function (): void {
    $priya = Member::factory()->create(['workspace_id' => $this->workspace->id]);

    $this->patchJson('/v1/members/'.$priya->id, ['data' => ['type' => 'members', 'id' => $priya->id, 'attributes' => ['role' => 'admin']]], [
        ...asMember($this->owner), 'If-Match' => etagOf('/v1/members/'.$priya->id, $this->owner),
    ])->assertOk()->assertJsonPath('data.attributes.role', 'admin');

    $this->patchJson('/v1/members/'.$this->owner->id, ['data' => ['type' => 'members', 'id' => $this->owner->id, 'attributes' => ['role' => 'member']]], [
        ...asMember($this->owner), 'If-Match' => etagOf('/v1/members/'.$this->owner->id, $this->owner),
    ])->assertStatus(409)->assertJsonPath('errors.0.code', 'last-owner');
});

it('lets a person edit only their own profile', function (): void {
    $priya = Member::factory()->create(['workspace_id' => $this->workspace->id]);
    $body = fn (Member $member) => ['data' => ['type' => 'members', 'id' => $member->id, 'attributes' => ['timezone' => 'Asia/Kolkata']]];

    $this->patchJson('/v1/members/'.$priya->id, $body($priya), [...asMember($priya, []), 'If-Match' => etagOf('/v1/members/'.$priya->id, $priya)])
        ->assertOk()->assertJsonPath('data.attributes.timezone', 'Asia/Kolkata');

    $this->patchJson('/v1/members/'.$priya->id, $body($priya), [...asMember($this->owner), 'If-Match' => etagOf('/v1/members/'.$priya->id, $this->owner)])
        ->assertForbidden();
});

it('leaves the workspace when a member deactivates themselves', function (): void {
    $priya = Member::factory()->create(['workspace_id' => $this->workspace->id]);

    $this->patchJson('/v1/members/'.$priya->id, ['data' => ['type' => 'members', 'id' => $priya->id, 'attributes' => ['status' => 'deactivated']]], [
        ...asMember($priya), 'If-Match' => etagOf('/v1/members/'.$priya->id, $priya),
    ])->assertOk();

    expect($priya->fresh()->status)->toBe(MemberStatus::Deactivated)
        ->and(AuditEvent::query()->where('action', 'member.leave')->exists())->toBeTrue();
});

it('creates, lists, resends and revokes invitations, never showing the token', function (): void {
    $created = $this->postJson('/v1/invitations', ['data' => ['type' => 'invitations', 'attributes' => ['email' => 'Priya@Acme.example', 'role' => 'member']]], asMember($this->owner))
        ->assertCreated()
        ->assertJsonPath('data.attributes.email', 'priya@acme.example')
        ->assertJsonPath('data.attributes.status', 'pending')
        ->assertJsonPath('data.relationships.invited_by.data.id', $this->owner->id)
        ->assertJsonMissingPath('data.attributes.token')
        ->assertJsonMissingPath('data.attributes.token_hash');
    $id = $created->json('data.id');

    $this->getJson('/v1/invitations?include=invited_by', asMember($this->owner))
        ->assertOk()->assertJsonPath('data.0.id', $id)->assertJsonPath('included.0.id', $this->owner->id);

    // Resending is an empty-bodied PATCH (RFC 0003).
    $this->patch('/v1/invitations/'.$id, [], [...asMember($this->owner), 'If-Match' => $created->headers->get('ETag')])->assertOk();

    $this->deleteJson('/v1/invitations/'.$id, [], [...asMember($this->owner), 'If-Match' => Versions::etag(Invitation::query()->findOrFail($id))])
        ->assertNoContent();

    expect(Invitation::query()->findOrFail($id)->status->value)->toBe('revoked');
});

it('keeps invitations to owners and admins', function (): void {
    $priya = Member::factory()->create(['workspace_id' => $this->workspace->id]);

    $this->getJson('/v1/invitations', asMember($priya))->assertForbidden();
});

it('lists the audit log newest first, with refusals, for owners and admins only', function (): void {
    $priya = Member::factory()->create(['workspace_id' => $this->workspace->id]);
    $this->postJson('/v1/invitations', ['data' => ['type' => 'invitations', 'attributes' => ['email' => 'sam@acme.example', 'role' => 'member']]], asMember($priya))
        ->assertForbidden();
    $this->postJson('/v1/invitations', ['data' => ['type' => 'invitations', 'attributes' => ['email' => 'kim@acme.example', 'role' => 'member']]], asMember($this->owner))
        ->assertCreated();

    $this->getJson('/v1/audit-events', asMember($this->owner))
        ->assertOk()
        ->assertJsonPath('data.0.attributes.action', 'member.invite')
        ->assertJsonPath('data.0.attributes.outcome', 'allowed')
        ->assertJsonPath('data.1.attributes.outcome', 'refused')
        ->assertJsonPath('data.1.relationships.actor.data.id', $priya->id);

    $this->getJson('/v1/audit-events?filter[outcome]=refused', asMember($this->owner))
        ->assertOk()->assertJsonCount(1, 'data');

    $this->getJson('/v1/audit-events?filter[occurred_after]=yesterday-ish', asMember($this->owner))->assertStatus(400);
    $this->getJson('/v1/audit-events', asMember($priya))->assertForbidden();
});

it('never shows one workspace the audit log of another', function (): void {
    $other = Member::factory()->owner()->create();
    AuditEvent::query()->create(['workspace_id' => $other->workspace_id, 'action' => 'member.invite', 'outcome' => 'allowed', 'surface' => 'rest', 'occurred_at' => now()]);

    $this->getJson('/v1/audit-events', asMember($this->owner))->assertOk()->assertJsonCount(0, 'data');
});
