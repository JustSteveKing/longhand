<?php

declare(strict_types=1);

namespace App\Auth;

use App\Http\Api\ApiActorResolver;
use Illuminate\Http\Request;
use Laravel\Passport\Client;
use League\OAuth2\Server\Exception\OAuthServerException;
use League\OAuth2\Server\ResourceServer;
use Longhand\Identity\Enums\MemberKind;
use Longhand\Identity\Models\Member;
use Longhand\Shared\Actors\Actor;
use Longhand\Shared\Actors\Surface;
use Symfony\Bridge\PsrHttpMessage\Factory\PsrHttpFactory;

/**
 * A REST request's actor from its OAuth access token (ADR 0060). A token
 * from the authorisation code flow belongs to the member chosen at
 * consent; a client-credentials token belongs to the agent that owns the
 * client. Revoked and expired tokens resolve to no one.
 */
final readonly class PassportActorResolver implements ApiActorResolver
{
    public function __construct(private ResourceServer $server) {}

    public function resolve(Request $request): ?Actor
    {
        if ($request->bearerToken() === null) {
            return null;
        }

        try {
            $validated = $this->server->validateAuthenticatedRequest((new PsrHttpFactory)->createRequest($request));
        } catch (OAuthServerException) {
            return null;
        }

        $clientId = $validated->getAttribute('oauth_client_id');
        $userId = $validated->getAttribute('oauth_user_id');
        $scopes = $validated->getAttribute('oauth_scopes');

        $memberId = is_string($userId) && str_starts_with($userId, 'mem_')
            ? $userId
            : $this->agentOwning(is_string($clientId) ? $clientId : null);

        $member = $memberId === null ? null : Member::query()->find($memberId);

        if ($member === null) {
            return null;
        }

        $granted = is_array($scopes) ? array_values(array_filter($scopes, 'is_string')) : [];

        // A client can ask for any scope Passport knows; an agent's token only
        // ever carries the scopes the agent was given (ADR 0016).
        if ($member->kind === MemberKind::Agent) {
            $granted = array_values(array_intersect($granted, $member->scopes));
        }

        return new Actor(
            memberId: $member->id,
            surface: Surface::Rest,
            scopes: $granted,
            onBehalfOf: $member->acts_on_behalf_of_id,
            accountId: $member->account_id,
        );
    }

    private function agentOwning(?string $clientId): ?string
    {
        if ($clientId === null) {
            return null;
        }

        $client = Client::query()->find($clientId);

        return $client !== null && $client->owner_type === (new TokenHolder)->getMorphClass() && is_string($client->owner_id)
            ? $client->owner_id
            : null;
    }
}
