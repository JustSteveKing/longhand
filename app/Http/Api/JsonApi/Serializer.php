<?php

declare(strict_types=1);

namespace App\Http\Api\JsonApi;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/**
 * Turns one kind of model into its resource object. Each implementation
 * handles one model class, and refuses any other.
 */
interface Serializer
{
    /**
     * The JSON:API type, plural and snake_case.
     */
    public function type(): string;

    /**
     * The path under /v1 of a resource of this type, such as `members/mem_...`.
     */
    public function path(Model $model): string;

    public function serialize(Model $model, Request $request): ResourceObject;

    /**
     * Relationship paths that may be included, each loading the related
     * models of a set of primary ones. Each loader must only return what
     * the caller can see (ADR 0012).
     *
     * @return array<string, \Closure(Collection<int, Model>, Request): iterable<Model>>
     */
    public function includes(): array;
}
