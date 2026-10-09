<?php

declare(strict_types=1);

namespace App\Providers;

use App\Http\Api\ApiActorResolver;
use App\Http\Api\JsonApi\Serializers;
use App\Http\Api\NoTokensYet;
use App\Http\Api\Serializers\AuditEventSerializer;
use App\Http\Api\Serializers\InvitationSerializer;
use App\Http\Api\Serializers\MemberSerializer;
use App\Http\Api\Serializers\WorkspaceSerializer;
use Illuminate\Support\ServiceProvider;
use Longhand\Identity\Audit\AuditEvent;
use Longhand\Identity\Models\Invitation;
use Longhand\Identity\Models\Member;
use Longhand\Identity\Models\Workspace;

/**
 * The REST surface: which serializer renders which model, and how a
 * request becomes an actor. Tokens arrive with Passport (ADR 0060).
 */
final class ApiServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(ApiActorResolver::class, NoTokensYet::class);

        $this->app->singleton(Serializers::class, fn ($app): Serializers => new Serializers($app, [
            Member::class => MemberSerializer::class,
            Workspace::class => WorkspaceSerializer::class,
            Invitation::class => InvitationSerializer::class,
            AuditEvent::class => AuditEventSerializer::class,
        ]));
    }
}
