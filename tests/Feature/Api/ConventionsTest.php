<?php

declare(strict_types=1);

use Longhand\Identity\Models\Member;

/*
 * RFC 0002's conventions, exercised through Identity's endpoints.
 */

beforeEach(function (): void {
    $this->owner = Member::factory()->owner()->create();
});

it('answers in JSON:API with a request id in the header and the document', function (): void {
    $response = $this->getJson('/v1/me', asMember($this->owner));

    $response->assertOk()
        ->assertHeader('Content-Type', 'application/vnd.api+json')
        ->assertJsonPath('jsonapi.version', '1.1')
        ->assertJsonPath('data.type', 'members')
        ->assertJsonPath('data.id', $this->owner->id)
        ->assertJsonPath('data.links.self', url('/v1/members/'.$this->owner->id));

    expect($response->json('meta.request_id'))->toBe($response->headers->get('X-Request-Id'));
});

it('echoes a request id the client sends', function (): void {
    $this->getJson('/v1/me', [...asMember($this->owner), 'X-Request-Id' => 'trace-123'])
        ->assertHeader('X-Request-Id', 'trace-123')
        ->assertJsonPath('meta.request_id', 'trace-123');
});

it('is 401 without a token, as a JSON:API error', function (): void {
    $this->getJson('/v1/me', ['Accept' => 'application/vnd.api+json'])
        ->assertUnauthorized()
        ->assertHeader('WWW-Authenticate', 'Bearer')
        ->assertJsonPath('errors.0.status', '401')
        ->assertJsonPath('errors.0.code', 'unauthorized')
        ->assertJsonPath('errors.0.links.type', 'https://apiguide.dev/errors/unauthorized');
});

it('is 415 for a body that is not JSON:API', function (): void {
    $this->call('POST', '/v1/invitations', [], [], [], [
        'HTTP_AUTHORIZATION' => 'Bearer test:'.$this->owner->id,
        'CONTENT_TYPE' => 'application/json',
        'HTTP_ACCEPT' => 'application/vnd.api+json',
    ], '{"data":{}}')
        ->assertStatus(415)
        ->assertJsonPath('errors.0.code', 'unsupported-media-type');
});

it('is 415 for an extension the endpoint does not support', function (): void {
    $this->call('POST', '/v1/invitations', [], [], [], [
        'HTTP_AUTHORIZATION' => 'Bearer test:'.$this->owner->id,
        'CONTENT_TYPE' => 'application/vnd.api+json; ext="https://jsonapi.org/ext/atomic"',
        'HTTP_ACCEPT' => 'application/vnd.api+json',
    ], '{"data":{}}')->assertStatus(415);
});

it('is 406 when Accept rules JSON:API out', function (string $accept): void {
    $this->getJson('/v1/me', [...asMember($this->owner), 'Accept' => $accept])
        ->assertStatus(406)
        ->assertJsonPath('errors.0.code', 'not-acceptable');
})->with(['text/html', 'application/vnd.api+json; charset=utf-8']);

it('is 400 for a query parameter the endpoint does not take', function (string $query, string $parameter): void {
    $this->getJson('/v1/members?'.$query, asMember($this->owner))
        ->assertStatus(400)
        ->assertJsonPath('errors.0.code', 'invalid-query-parameter')
        ->assertJsonPath('errors.0.source.parameter', $parameter);
})->with([
    ['q=priya', 'q'],
    ['fields[members]=handle', 'fields'],
    ['filter[status]=active', 'filter[status]'],
    ['sort=-status', 'sort'],
    ['include=spaces', 'include'],
]);

it('reports the rate limit with the IETF headers', function (): void {
    $response = $this->getJson('/v1/me', asMember($this->owner));

    expect($response->headers->get('RateLimit-Policy'))->toBe('"default";q=600;w=60')
        ->and($response->headers->get('RateLimit'))->toMatch('/^"default";r=\d+;t=\d+$/');
});

it('pages with the cursor profile, binding cursors to their query', function (): void {
    Member::factory()->count(4)->create(['workspace_id' => $this->owner->workspace_id]);

    $first = $this->getJson('/v1/members?page[size]=2', asMember($this->owner))
        ->assertOk()
        ->assertHeader('Content-Type', 'application/vnd.api+json; profile="https://jsonapi.org/profiles/ethanresnick/cursor-pagination"')
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('links.prev', null);

    $next = $first->json('links.next');
    $second = $this->getJson($next, asMember($this->owner))->assertOk()->assertJsonCount(2, 'data');

    expect(array_intersect(array_column($first->json('data'), 'id'), array_column($second->json('data'), 'id')))->toBe([]);

    parse_str((string) parse_url($next, PHP_URL_QUERY), $query);
    $this->getJson('/v1/members?sort=handle&page[after]='.$query['page']['after'], asMember($this->owner))
        ->assertStatus(400)
        ->assertJsonPath('errors.0.code', 'invalid-pagination-cursor');
});

it('refuses a page over 200 with the profile\'s own error', function (): void {
    $this->getJson('/v1/members?page[size]=201', asMember($this->owner))
        ->assertStatus(400)
        ->assertJsonPath('errors.0.links.type', 'https://jsonapi.org/profiles/ethanresnick/cursor-pagination/max-size-exceeded')
        ->assertJsonPath('errors.0.meta.page.maxSize', 200);
});

it('requires If-Match on PATCH: 428 without, 412 when stale', function (): void {
    $body = ['data' => ['type' => 'workspaces', 'id' => $this->owner->workspace_id, 'attributes' => ['name' => 'Acme Ltd']]];

    $this->patchJson('/v1/workspace', $body, asMember($this->owner))
        ->assertStatus(428)
        ->assertJsonPath('errors.0.code', 'precondition-required')
        ->assertJsonPath('errors.0.source.header', 'If-Match');

    $this->patchJson('/v1/workspace', $body, [...asMember($this->owner), 'If-Match' => '"stale"'])
        ->assertStatus(412)
        ->assertHeader('ETag');

    $etag = $this->getJson('/v1/workspace', asMember($this->owner))->headers->get('ETag');

    $this->patchJson('/v1/workspace', $body, [...asMember($this->owner), 'If-Match' => $etag])
        ->assertOk()
        ->assertJsonPath('data.attributes.name', 'Acme Ltd');
});

it('replays a POST with the same Idempotency-Key, and refuses one with a different body', function (): void {
    $headers = [...asMember($this->owner), 'Idempotency-Key' => 'invite-priya'];
    $body = ['data' => ['type' => 'invitations', 'attributes' => ['email' => 'priya@acme.example', 'role' => 'member']]];

    $first = $this->postJson('/v1/invitations', $body, $headers)->assertCreated();
    $replay = $this->postJson('/v1/invitations', $body, $headers)->assertCreated()->assertHeader('Idempotent-Replayed', 'true');

    expect($replay->json('data.id'))->toBe($first->json('data.id'));

    $body['data']['attributes']['email'] = 'sam@acme.example';
    $this->postJson('/v1/invitations', $body, $headers)
        ->assertStatus(409)
        ->assertJsonPath('errors.0.code', 'idempotency-key-conflict');
});

it('points validation errors at the field', function (): void {
    $this->postJson('/v1/invitations', ['data' => ['type' => 'invitations', 'attributes' => ['email' => 'nope', 'role' => 'member']]], asMember($this->owner))
        ->assertStatus(422)
        ->assertJsonPath('errors.0.code', 'validation-failed')
        ->assertJsonPath('errors.0.source.pointer', '/data/attributes/email');
});

it('is 400 for a body that is not a JSON:API document', function (): void {
    $this->postJson('/v1/invitations', ['email' => 'priya@acme.example'], asMember($this->owner))
        ->assertStatus(400)
        ->assertJsonPath('errors.0.code', 'malformed-request-body');
});

it('is 404, never 403, for something in another workspace', function (): void {
    $stranger = Member::factory()->create();

    $this->getJson('/v1/members/'.$stranger->id, asMember($this->owner))
        ->assertNotFound()
        ->assertJsonPath('errors.0.code', 'resource-not-found');
});

it('is 403 with the action and scope named when the token lacks it', function (): void {
    $this->getJson('/v1/audit-events', asMember($this->owner, ['members:read']))
        ->assertForbidden()
        ->assertHeader('WWW-Authenticate', 'Bearer error="insufficient_scope", scope="audit:read"')
        ->assertJsonPath('errors.0.code', 'insufficient-scope')
        ->assertJsonPath('errors.0.meta.action', 'audit.read');
});

it('renders domain errors with their own code and meta', function (): void {
    $this->postJson('/v1/members', ['data' => ['type' => 'members', 'attributes' => [
        'kind' => 'agent', 'display_name' => 'Triage', 'handle' => 'triage', 'scopes' => ['audit:read'],
    ]]], asMember($this->owner))
        ->assertStatus(422)
        ->assertJsonPath('errors.0.code', 'scope-exceeds-owner')
        ->assertJsonPath('errors.0.links.type', 'https://api.longhand.example/problems/scope-exceeds-owner')
        ->assertJsonPath('errors.0.meta.scopes', ['audit:read']);
});
