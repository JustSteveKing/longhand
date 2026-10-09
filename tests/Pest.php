<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Longhand\Identity\Authorisation\RoleScopes;
use Longhand\Identity\Enums\Role;
use Longhand\Identity\Models\Member;
use Longhand\Shared\Actions\ActionRunner;
use Longhand\Shared\Actors\Actor;
use Longhand\Shared\Actors\Surface;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind different classes or traits.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

/**
 * The actor a member's own session or token would give, carrying every
 * scope their role allows unless told otherwise.
 *
 * @param  list<string>|null  $scopes
 */
function memberActor(Member $member, ?array $scopes = null, Surface $surface = Surface::Rest): Actor
{
    return new Actor(
        memberId: $member->id,
        surface: $surface,
        scopes: $scopes ?? RoleScopes::for($member->role ?? Role::Owner),
        accountId: $member->account_id,
    );
}

/**
 * An account in the web app before it is a member of a workspace.
 */
function accountActor(User $account): Actor
{
    return new Actor(memberId: null, surface: Surface::Web, accountId: $account->id);
}

/**
 * Headers for a JSON:API request as a member.
 *
 * @param  list<string>|null  $scopes
 * @return array<string, string>
 */
function asMember(Member $member, ?array $scopes = null): array
{
    return [
        'Authorization' => 'Bearer test:'.$member->id.($scopes === null ? '' : ':'.implode(',', $scopes)),
        'Accept' => 'application/vnd.api+json',
        'Content-Type' => 'application/vnd.api+json',
    ];
}

function runAction(Actor $actor, string $action, object $payload): mixed
{
    return app(ActionRunner::class)->run($actor, $action, $payload);
}
