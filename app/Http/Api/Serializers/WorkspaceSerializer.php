<?php

declare(strict_types=1);

namespace App\Http\Api\Serializers;

use App\Http\Api\JsonApi\ResourceObject;
use App\Http\Api\JsonApi\Serializer;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use LogicException;
use Longhand\Identity\Models\Workspace;

final class WorkspaceSerializer implements Serializer
{
    public function type(): string
    {
        return 'workspaces';
    }

    public function path(Model $model): string
    {
        return 'workspace';
    }

    public function serialize(Model $model, Request $request): ResourceObject
    {
        $model = $model instanceof Workspace ? $model : throw new LogicException('Expected a Workspace.');

        return new ResourceObject('workspaces', $model->id, [
            'name' => $model->name,
            'handle' => $model->handle,
            'default_timezone' => $model->default_timezone,
            'max_agents_per_member' => $model->max_agents_per_member,
            'max_assistants_per_member' => $model->max_assistants_per_member,
            'brief_daily_limit' => $model->brief_daily_limit,
            'semantic_search' => $model->semantic_search,
            'subscription_approval' => $model->subscription_approval,
            'created_at' => $model->created_at->toIso8601ZuluString(),
        ], [
            // The built-in generator arrives with Briefs (RFC 0007).
            'brief_generator' => ResourceObject::toOne('members', null),
        ]);
    }

    public function includes(): array
    {
        return [];
    }
}
