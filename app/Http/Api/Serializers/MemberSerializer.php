<?php

declare(strict_types=1);

namespace App\Http\Api\Serializers;

use App\Http\Api\JsonApi\ResourceObject;
use App\Http\Api\JsonApi\Serializer;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use LogicException;
use Longhand\Identity\Enums\MemberKind;
use Longhand\Identity\Models\Member;

final class MemberSerializer implements Serializer
{
    public function type(): string
    {
        return 'members';
    }

    public function path(Model $model): string
    {
        return 'members/'.$model->getKey();
    }

    public function serialize(Model $model, Request $request): ResourceObject
    {
        $model = $model instanceof Member ? $model : throw new LogicException('Expected a Member.');
        $agent = $model->kind === MemberKind::Agent;

        return new ResourceObject('members', $model->id, [
            'kind' => $model->kind->value,
            'display_name' => $model->display_name,
            'handle' => $model->handle,
            'role' => $model->role?->value,
            'status' => $model->status->value,
            'timezone' => $model->timezone,
            // Attention (RFC 0006) provides the real summary; until then a
            // person shows no next window, and an agent is always in one.
            'availability' => ['in_window' => $agent, 'next_window_starts_at' => null],
            'description' => $agent ? $model->description : null,
            'scopes' => $agent ? $model->scopes : null,
            'requires_approval_for' => $agent ? $model->requires_approval_for : null,
            'model' => $agent ? $model->model : null,
            'assistant' => $agent ? $model->assistant : null,
            'spaces_follow_principal' => $agent ? $model->spaces_follow_principal : null,
            'created_at' => $model->created_at->toIso8601ZuluString(),
        ], [
            'owner' => ResourceObject::toOne('members', $model->owner_id),
            'spaces' => ResourceObject::toMany('spaces', $agent ? $model->space_ids : []),
            'acts_on_behalf_of' => ResourceObject::toOne('members', $model->acts_on_behalf_of_id),
        ]);
    }

    public function includes(): array
    {
        return [
            'owner' => fn ($members) => Member::query()
                ->whereIn('id', $members->pluck('owner_id')->filter())
                ->get(),
        ];
    }
}
