<?php

declare(strict_types=1);

namespace App\Http\Api\Serializers;

use App\Http\Api\JsonApi\ResourceObject;
use App\Http\Api\JsonApi\Serializer;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use LogicException;
use Longhand\Identity\Enums\InvitationStatus;
use Longhand\Identity\Models\Invitation;
use Longhand\Identity\Models\Member;

final class InvitationSerializer implements Serializer
{
    public function type(): string
    {
        return 'invitations';
    }

    public function path(Model $model): string
    {
        return 'invitations/'.$model->getKey();
    }

    public function serialize(Model $model, Request $request): ResourceObject
    {
        $model = $model instanceof Invitation ? $model : throw new LogicException('Expected a Invitation.');
        // A pending invitation past its 7 days is expired, even before the
        // hourly schedule marks it so.
        $status = $model->status === InvitationStatus::Pending && ! $model->isPending()
            ? InvitationStatus::Expired
            : $model->status;

        return new ResourceObject('invitations', $model->id, [
            'email' => $model->email,
            'role' => $model->role->value,
            'status' => $status->value,
            'expires_at' => $model->expires_at->toIso8601ZuluString(),
            'created_at' => $model->created_at->toIso8601ZuluString(),
        ], [
            'invited_by' => ResourceObject::toOne('members', $model->invited_by_id),
            'spaces' => ResourceObject::toMany('spaces', $model->space_ids),
        ]);
    }

    public function includes(): array
    {
        return [
            'invited_by' => fn ($invitations) => Member::query()->whereIn('id', $invitations->pluck('invited_by_id'))->get(),
        ];
    }
}
