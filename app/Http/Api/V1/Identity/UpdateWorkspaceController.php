<?php

declare(strict_types=1);

namespace App\Http\Api\V1\Identity;

use App\Http\Api\ApiActor;
use App\Http\Api\CurrentMember;
use App\Http\Api\JsonApi\Document;
use App\Http\Api\JsonApi\QueryParameters;
use App\Http\Api\JsonApi\RequestDocument;
use App\Http\Api\JsonApi\Versions;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Longhand\Identity\Features\UpdateWorkspace\UpdateWorkspace;
use Longhand\Identity\Features\UpdateWorkspace\UpdateWorkspacePayload;
use Longhand\Identity\Handle;
use Longhand\Identity\Models\Workspace;
use Longhand\Shared\Actions\ActionRunner;

final readonly class UpdateWorkspaceController
{
    public function __construct(private Document $document, private ActionRunner $runner) {}

    public function __invoke(Request $request): JsonResponse
    {
        $parameters = QueryParameters::from($request);
        $workspace = Workspace::query()->findOrFail(CurrentMember::of($request)->workspace_id);
        $input = RequestDocument::from($request, 'workspaces', $workspace->id);

        Versions::require($request, $workspace);

        $attributes = $input->validate([
            'name' => ['sometimes', 'string', 'min:1', 'max:120'],
            'handle' => ['sometimes', 'string'],
            'default_timezone' => ['sometimes', 'timezone:all'],
            'max_agents_per_member' => ['sometimes', 'integer', 'min:0', 'max:100'],
            'max_assistants_per_member' => ['sometimes', 'integer', 'min:0', 'max:100'],
            'brief_daily_limit' => ['sometimes', 'integer', 'min:1', 'max:500'],
            'semantic_search' => ['sometimes', 'boolean'],
            'subscription_approval' => ['sometimes', 'boolean'],
        ]);

        $updated = $this->runner->run(ApiActor::of($request), UpdateWorkspace::class, new UpdateWorkspacePayload(
            name: $attributes['name'] ?? null,
            handle: isset($attributes['handle']) ? Handle::from($attributes['handle']) : null,
            defaultTimezone: $attributes['default_timezone'] ?? null,
            maxAgentsPerMember: $attributes['max_agents_per_member'] ?? null,
            maxAssistantsPerMember: $attributes['max_assistants_per_member'] ?? null,
            briefDailyLimit: $attributes['brief_daily_limit'] ?? null,
            semanticSearch: $attributes['semantic_search'] ?? null,
            subscriptionApproval: $attributes['subscription_approval'] ?? null,
        ));

        return $this->document->resource($request, $updated, $parameters);
    }
}
