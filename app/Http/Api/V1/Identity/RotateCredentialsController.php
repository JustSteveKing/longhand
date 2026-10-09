<?php

declare(strict_types=1);

namespace App\Http\Api\V1\Identity;

use App\Http\Api\ApiActor;
use App\Http\Api\CurrentMember;
use App\Http\Api\JsonApi\Document;
use App\Http\Api\JsonApi\QueryParameters;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Longhand\Identity\Credentials\IssuedCredentials;
use Longhand\Identity\Features\ConfigureAgent\AgentPayload;
use Longhand\Identity\Features\RotateAgentCredentials\RotateAgentCredentials;
use Longhand\Identity\Models\Member;
use Longhand\Shared\Actions\ActionRunner;

/**
 * POST /v1/members/{member}/credentials: a new client secret for an agent,
 * shown once (RFC 0003).
 */
final readonly class RotateCredentialsController
{
    public function __construct(private Document $document, private ActionRunner $runner) {}

    public function __invoke(Request $request, string $member): JsonResponse
    {
        $parameters = QueryParameters::from($request);
        $agent = Member::query()->where('workspace_id', CurrentMember::of($request)->workspace_id)->findOrFail($member);

        /** @var IssuedCredentials $issued */
        $issued = $this->runner->run(ApiActor::of($request), RotateAgentCredentials::class, new AgentPayload($agent->id));

        return $this->document->resource($request, $issued->agent, $parameters, meta: [
            'client_credentials' => [
                'client_id' => $issued->credentials->clientId,
                'client_secret' => $issued->credentials->clientSecret,
            ],
        ]);
    }
}
