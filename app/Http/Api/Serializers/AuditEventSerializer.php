<?php

declare(strict_types=1);

namespace App\Http\Api\Serializers;

use App\Http\Api\JsonApi\ResourceObject;
use App\Http\Api\JsonApi\Serializer;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use LogicException;
use Longhand\Identity\Audit\AuditEvent;
use Longhand\Identity\Models\Member;

final class AuditEventSerializer implements Serializer
{
    public function type(): string
    {
        return 'audit_events';
    }

    public function path(Model $model): string
    {
        return 'audit-events/'.$model->getKey();
    }

    public function serialize(Model $model, Request $request): ResourceObject
    {
        $model = $model instanceof AuditEvent ? $model : throw new LogicException('Expected a AuditEvent.');

        return new ResourceObject('audit_events', $model->id, [
            'action' => $model->action,
            'outcome' => $model->outcome->value,
            'surface' => $model->surface->value,
            'scope' => $model->scope,
            'occurred_at' => $model->occurred_at->toIso8601ZuluString(),
            'changes' => $model->changes,
        ], [
            'actor' => ResourceObject::toOne('members', $model->actor_id),
            'on_behalf_of' => ResourceObject::toOne('members', $model->on_behalf_of_id),
            'subject' => $model->subject_type === null ? ['data' => null] : ResourceObject::toOne($model->subject_type, $model->subject_id),
        ]);
    }

    public function includes(): array
    {
        return [
            'actor' => fn ($events) => Member::query()->whereIn('id', $events->pluck('actor_id')->filter())->get(),
        ];
    }
}
