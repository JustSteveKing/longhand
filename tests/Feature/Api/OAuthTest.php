<?php

declare(strict_types=1);

use App\Auth\PassportActorResolver;
use App\Auth\WorkspaceMemberGuard;
use App\Http\Api\ApiActorResolver;
use App\Models\User;
use Illuminate\Support\Once;
use Illuminate\Support\Str;
use Laravel\Passport\ClientRepository;
use Longhand\Identity\Credentials\IssuedCredentials;
use Longhand\Identity\Features\ChangeRole\MemberPayload;
use Longhand\Identity\Features\ConfigureAgent\AgentPayload;
use Longhand\Identity\Features\CreateAgent\CreateAgent;
use Longhand\Identity\Features\CreateAgent\CreateAgentPayload;
use Longhand\Identity\Features\DeactivateMember\DeactivateMember;
use Longhand\Identity\Features\RotateAgentCredentials\RotateAgentCredentials;
use Longhand\Identity\Handle;
use Longhand\Identity\Models\Member;
use Tests\Support\FakeTokens;

/*
 * Real Passport tokens, end to end (ADR 0060): no fake resolver here.
 */

beforeEach(function (): void {
    $this->app->bind(ApiActorResolver::class, PassportActorResolver::class);
    $this->owner = Member::factory()->owner()->create();
});

function issueAgent(Member $owner, array $scopes = ['threads:read', 'members:read']): IssuedCredentials
{
    return runAction(memberActor($owner), CreateAgent::class, new CreateAgentPayload(
        displayName: 'Triage',
        handle: Handle::from('triage-'.Str::lower(Str::random(6))),
        scopes: $scopes,
    ));
}

function clientCredentialsToken(IssuedCredentials $issued, string $scope = 'members:read'): string
{
    return test()->postJson('/oauth/token', [
        'grant_type' => 'client_credentials',
        'client_id' => $issued->credentials->clientId,
        'client_secret' => $issued->credentials->clientSecret,
        'scope' => $scope,
    ])->assertOk()->json('access_token');
}

function bearer(string $token): array
{
    return ['Authorization' => 'Bearer '.$token, 'Accept' => 'application/vnd.api+json'];
}

it('gives an agent a client-credentials token that acts as the agent', function (): void {
    $issued = issueAgent($this->owner);

    $this->getJson('/v1/me', bearer(clientCredentialsToken($issued)))
        ->assertOk()
        ->assertJsonPath('data.id', $issued->agent->id)
        ->assertJsonPath('data.attributes.kind', 'agent');
});

it('never lets an agent\'s token carry a scope the agent was not given', function (): void {
    $issued = issueAgent($this->owner, ['threads:read']);

    $this->getJson('/v1/members', bearer(clientCredentialsToken($issued, 'members:read threads:read')))
        ->assertForbidden()
        ->assertJsonPath('errors.0.code', 'insufficient-scope');
});

it('shows the agent\'s credentials once, when it is created through the API', function (): void {
    $this->app->bind(ApiActorResolver::class, FakeTokens::class);

    $response = $this->postJson('/v1/members', ['data' => ['type' => 'members', 'attributes' => [
        'kind' => 'agent', 'display_name' => 'Triage', 'handle' => 'triage', 'scopes' => ['members:read'],
    ]]], asMember($this->owner))->assertCreated();

    expect($response->json('meta.client_credentials.client_id'))->toBeString()
        ->and($response->json('meta.client_credentials.client_secret'))->toBeString();
    $this->getJson('/v1/members/'.$response->json('data.id'), asMember($this->owner))
        ->assertJsonMissingPath('meta.client_credentials');
});

it('revokes the old tokens when an agent\'s credentials are rotated', function (): void {
    $issued = issueAgent($this->owner);
    $token = clientCredentialsToken($issued);

    $rotated = runAction(memberActor($this->owner), RotateAgentCredentials::class, new AgentPayload($issued->agent->id));

    // Passport memoises clients with once(), which lasts as long as the app
    // instance: across every request of a test, but flushed between requests
    // in production, Octane included. Flush it as a new request would.
    Once::flush();

    expect($rotated->credentials->clientSecret)->not->toBe($issued->credentials->clientSecret);
    $this->getJson('/v1/me', bearer($token))->assertUnauthorized();
    $this->getJson('/v1/me', bearer(clientCredentialsToken($rotated)))->assertOk();
});

it('revokes a member\'s tokens, and their agents\', when the membership ends', function (): void {
    $priya = Member::factory()->create(['workspace_id' => $this->owner->workspace_id]);
    $issued = issueAgent($priya);
    $token = clientCredentialsToken($issued);

    runAction(memberActor($this->owner), DeactivateMember::class, new MemberPayload($priya->id));

    $this->getJson('/v1/me', bearer($token))->assertUnauthorized();
});

it('grants an authorisation-code token to the member in the workspace the account is using', function (): void {
    $account = User::query()->findOrFail($this->owner->account_id);
    $elsewhere = Member::factory()->create(['account_id' => $account->id]);
    $client = app(ClientRepository::class)->createAuthorizationCodeGrantClient('Linear sync', ['https://app.example/callback'], confidential: false);
    $verifier = Str::random(64);
    $challenge = rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '=');

    $this->actingAs($account)->withSession([WorkspaceMemberGuard::SESSION_KEY => $elsewhere->workspace_id]);

    $consent = $this->get('/oauth/authorize?'.http_build_query([
        'client_id' => $client->getKey(),
        'redirect_uri' => 'https://app.example/callback',
        'response_type' => 'code',
        'scope' => 'members:read',
        'state' => 'xyz',
        'code_challenge' => $challenge,
        'code_challenge_method' => 'S256',
    ]))->assertOk();

    $consent->assertInertia(fn ($page) => $page->component('oauth/authorize')
        ->where('client.name', 'Linear sync')
        ->where('workspace.name', $elsewhere->workspace->name)
        ->where('scopes.0.id', 'members:read'));

    $redirect = $this->post('/oauth/authorize', [
        'state' => 'xyz',
        'client_id' => $client->getKey(),
        'auth_token' => session('authToken'),
    ])->assertRedirect();

    parse_str((string) parse_url($redirect->headers->get('Location'), PHP_URL_QUERY), $query);

    $token = $this->postJson('/oauth/token', [
        'grant_type' => 'authorization_code',
        'client_id' => $client->getKey(),
        'redirect_uri' => 'https://app.example/callback',
        'code_verifier' => $verifier,
        'code' => $query['code'],
    ])->assertOk()->json('access_token');

    $this->getJson('/v1/me', bearer($token))->assertOk()->assertJsonPath('data.id', $elsewhere->id);
});

it('is 401 for a token that is not one', function (): void {
    $this->getJson('/v1/me', bearer('not-a-token'))->assertUnauthorized();
});
