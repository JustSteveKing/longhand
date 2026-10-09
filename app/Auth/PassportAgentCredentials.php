<?php

declare(strict_types=1);

namespace App\Auth;

use Illuminate\Support\Facades\DB;
use Laravel\Passport\Client;
use Laravel\Passport\ClientRepository;
use LogicException;
use Longhand\Identity\Credentials\AgentCredentials;
use Longhand\Identity\Credentials\ClientCredentials;
use Longhand\Identity\Models\Member;

/**
 * An agent's client-credentials client, owned by the agent (ADR 0060).
 */
final readonly class PassportAgentCredentials implements AgentCredentials
{
    public function __construct(private ClientRepository $clients) {}

    public function issue(Member $agent): ClientCredentials
    {
        $client = $this->clients->createClientCredentialsGrantClient($agent->display_name);
        $client->forceFill(['owner_type' => (new TokenHolder)->getMorphClass(), 'owner_id' => $agent->id])->save();

        return $this->credentials($client);
    }

    public function rotate(Member $agent): ClientCredentials
    {
        $client = Client::query()
            ->where('owner_type', (new TokenHolder)->getMorphClass())
            ->where('owner_id', $agent->id)
            ->where('revoked', false)
            ->latest()
            ->first() ?? throw new LogicException('This agent has no client to rotate.');

        $this->clients->regenerateSecret($client);

        DB::table('oauth_access_tokens')->where('client_id', $client->id)->update(['revoked' => true]);

        return $this->credentials($client);
    }

    private function credentials(Client $client): ClientCredentials
    {
        return new ClientCredentials(
            (string) $client->getKey(),
            $client->plainSecret ?? throw new LogicException('The client secret was not generated.'),
        );
    }
}
