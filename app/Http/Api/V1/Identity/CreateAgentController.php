<?php

declare(strict_types=1);

namespace App\Http\Api\V1\Identity;

use App\Http\Api\ApiActor;
use App\Http\Api\JsonApi\Document;
use App\Http\Api\JsonApi\QueryParameters;
use App\Http\Api\JsonApi\RequestDocument;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Longhand\Identity\Credentials\IssuedCredentials;
use Longhand\Identity\Features\CreateAgent\CreateAgent;
use Longhand\Identity\Features\CreateAgent\CreateAgentPayload;
use Longhand\Identity\Handle;
use Longhand\Shared\Actions\ActionRunner;

/**
 * POST /v1/members creates an agent; people join by invitation, never
 * through the API (RFC 0003). Its client credentials come back once.
 */
final readonly class CreateAgentController
{
    public function __construct(private Document $document, private ActionRunner $runner) {}

    public function __invoke(Request $request): JsonResponse
    {
        $parameters = QueryParameters::from($request);
        $input = RequestDocument::from($request, 'members');

        $attributes = $input->validate([
            'kind' => ['required', 'in:agent'],
            'display_name' => ['required', 'string', 'min:1', 'max:120'],
            'handle' => ['required', 'string'],
            'description' => ['nullable', 'string', 'max:500'],
            'scopes' => ['sometimes', 'array'],
            'scopes.*' => ['string'],
            'requires_approval_for' => ['sometimes', 'array'],
            'requires_approval_for.*' => ['string'],
            'model' => ['nullable', 'array:provider,name'],
            'model.provider' => ['required_with:model', 'string'],
            'model.name' => ['required_with:model', 'string'],
            'timezone' => ['sometimes', 'timezone:all'],
        ]);

        /** @var IssuedCredentials $issued */
        $issued = $this->runner->run(ApiActor::of($request), CreateAgent::class, new CreateAgentPayload(
            displayName: $attributes['display_name'],
            handle: Handle::from($attributes['handle']),
            scopes: array_values($attributes['scopes'] ?? []),
            requiresApprovalFor: array_values($attributes['requires_approval_for'] ?? []),
            spaceIds: $input->toMany('spaces', 'spaces'),
            description: $attributes['description'] ?? null,
            model: $attributes['model'] ?? null,
            ownerId: $input->toOne('owner', 'members'),
            timezone: $attributes['timezone'] ?? null,
        ));

        return $this->document->resource($request, $issued->agent, $parameters, 201, [
            'Location' => url('/v1/members/'.$issued->agent->id),
        ], meta: [
            // Shown once; Longhand keeps only a hash of the secret (RFC 0003).
            'client_credentials' => [
                'client_id' => $issued->credentials->clientId,
                'client_secret' => $issued->credentials->clientSecret,
            ],
        ]);
    }
}
