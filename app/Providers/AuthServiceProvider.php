<?php

declare(strict_types=1);

namespace App\Providers;

use App\Auth\PassportAccessTokens;
use App\Auth\PassportActorResolver;
use App\Auth\PassportAgentCredentials;
use App\Auth\TokenHolder;
use App\Auth\WorkspaceMemberGuard;
use App\Http\Api\ApiActorResolver;
use Carbon\CarbonInterval;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\ServiceProvider;
use Inertia\Inertia;
use Laravel\Passport\Passport;
use Longhand\Identity\Credentials\AccessTokens;
use Longhand\Identity\Credentials\AgentCredentials;
use Longhand\Identity\Models\Member;
use Longhand\Identity\Models\Workspace;

/**
 * OAuth 2.1 through Passport, for REST and later MCP (ADR 0059, ADR 0060):
 * tokens belong to members, agents use client credentials, access tokens
 * last an hour and refresh tokens rotate on every use.
 */
final class AuthServiceProvider extends ServiceProvider
{
    /** The scopes from RFC 0003, as the consent screen shows them. */
    public const array SCOPES = [
        'workspace:read' => 'Read the workspace\'s name, handle and settings',
        'workspace:write' => 'Change the workspace\'s settings',
        'members:read' => 'Read members and their availability summary',
        'members:write' => 'Invite people, change roles and manage agents',
        'spaces:read' => 'See spaces and who is in them',
        'spaces:write' => 'Create, change and archive spaces, and manage who is in them',
        'threads:read' => 'Read threads and everything in them',
        'threads:write' => 'Start threads and change their status, owner and deadlines',
        'posts:write:draft' => 'Write posts as drafts only',
        'posts:write' => 'Write, edit and publish posts',
        'requests:write' => 'Make requests and move them along',
        'requests:assign' => 'Assign requests to other people',
        'decisions:write' => 'Draft decisions, and publish and supersede them',
        'inbox:read' => 'Read the inbox',
        'inbox:write' => 'Mark inbox items done, snooze and reopen them',
        'briefs:read' => 'Read briefs and roll-ups',
        'briefs:write' => 'Ask for briefs and roll-ups',
        'check_ins:read' => 'Read check-ins and their answers',
        'check_ins:write' => 'Set up check-ins and answer them',
        'availability:write' => 'Change working hours and away periods',
        'webhooks:write' => 'Manage webhook subscriptions',
        'audit:read' => 'Read the audit log',
    ];

    public function register(): void
    {
        $this->app->bind(ApiActorResolver::class, PassportActorResolver::class);
        $this->app->bind(AgentCredentials::class, PassportAgentCredentials::class);
        $this->app->bind(AccessTokens::class, PassportAccessTokens::class);
    }

    public function boot(): void
    {
        Relation::morphMap(['member' => TokenHolder::class]);

        Passport::tokensCan(self::SCOPES);
        Passport::tokensExpireIn(CarbonInterval::hour());
        Passport::refreshTokensExpireIn(CarbonInterval::days(30));

        // The consent screen: which workspace, which scopes, in words (RFC 0012).
        Passport::authorizationView(fn (array $parameters) => Inertia::render('oauth/authorize', [
            'client' => ['id' => $parameters['client']->getKey(), 'name' => $parameters['client']->name],
            'scopes' => array_map(fn ($scope) => ['id' => $scope->id, 'description' => $scope->description], $parameters['scopes']),
            'workspace' => ['name' => Workspace::query()->whereKey(Member::query()->whereKey($parameters['user']->getAuthIdentifier())->value('workspace_id'))->value('name')],
            'authToken' => $parameters['authToken'],
            'state' => $parameters['request']->string('state')->value(),
            'csrfToken' => csrf_token(),
        ])->toResponse($parameters['request']));

        Auth::extend('workspace-member', fn ($app) => new WorkspaceMemberGuard(Auth::guard('web'), $app['session.store']));
    }
}
