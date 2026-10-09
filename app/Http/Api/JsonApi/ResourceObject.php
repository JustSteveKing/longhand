<?php

declare(strict_types=1);

namespace App\Http\Api\JsonApi;

/**
 * One JSON:API resource object (RFC 0002). Every attribute is present,
 * with null for no value; references to other resources are
 * relationships, never attributes.
 */
final readonly class ResourceObject
{
    /**
     * @param  array<string, mixed>  $attributes
     * @param  array<string, array<string, mixed>>  $relationships
     * @param  array<string, mixed>  $meta
     */
    public function __construct(
        public string $type,
        public string $id,
        public array $attributes,
        public array $relationships = [],
        public array $meta = [],
    ) {}

    /**
     * @return array{type: string, id: string}|null
     */
    public static function identifier(string $type, ?string $id): ?array
    {
        return $id === null ? null : ['type' => $type, 'id' => $id];
    }

    /**
     * @return array{data: array{type: string, id: string}|null}
     */
    public static function toOne(string $type, ?string $id): array
    {
        return ['data' => self::identifier($type, $id)];
    }

    /**
     * @param  iterable<string>  $ids
     * @return array{data: list<array{type: string, id: string}>}
     */
    public static function toMany(string $type, iterable $ids): array
    {
        $data = [];

        foreach ($ids as $id) {
            $data[] = ['type' => $type, 'id' => $id];
        }

        return ['data' => $data];
    }

    /**
     * A to-many relationship too large to embed carries only a link (RFC 0002).
     *
     * @return array{links: array{related: string}}
     */
    public static function related(string $path): array
    {
        return ['links' => ['related' => $path]];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(string $selfUrl): array
    {
        return array_filter([
            'type' => $this->type,
            'id' => $this->id,
            'attributes' => $this->attributes,
            'relationships' => $this->relationships === [] ? null : $this->relationships,
            'links' => ['self' => $selfUrl],
            'meta' => $this->meta === [] ? null : $this->meta,
        ], fn ($value) => $value !== null);
    }
}
