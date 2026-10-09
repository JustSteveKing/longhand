<?php

declare(strict_types=1);

namespace App\Http\Api\JsonApi;

use App\Exceptions\ErrorCode;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * A JSON:API request document's primary data, checked for shape (400) and
 * then for content (422, one error per field with a pointer).
 */
final readonly class RequestDocument
{
    /**
     * @param  array<string, mixed>  $attributes
     * @param  array<string, mixed>  $relationships
     * @param  array<string, mixed>  $meta
     */
    private function __construct(
        public array $attributes,
        public array $relationships,
        public array $meta,
    ) {}

    /**
     * @param  string|null  $id  For an update: the id the document must carry.
     */
    public static function from(Request $request, string $type, ?string $id = null, bool $optional = false): self
    {
        $body = $request->getContent();

        if ($body === '' && $optional) {
            return new self([], [], []);
        }

        $document = json_decode($body, true);

        if (! is_array($document) || ! is_array($document['data'] ?? null) || array_is_list($document['data'])) {
            throw self::malformed('The body must be a JSON:API document with a resource object as its data.', '');
        }

        $data = $document['data'];

        if (($data['type'] ?? null) !== $type) {
            throw self::malformed("data.type must be {$type}.", '/data/type');
        }

        if ($id === null && array_key_exists('id', $data)) {
            throw self::malformed('Identifiers are made by the server; leave out data.id.', '/data/id');
        }

        if ($id !== null && ($data['id'] ?? null) !== $id) {
            throw self::malformed("data.id must be {$id}.", '/data/id');
        }

        foreach (['attributes', 'relationships'] as $member) {
            if (isset($data[$member]) && (! is_array($data[$member]) || ($data[$member] !== [] && array_is_list($data[$member])))) {
                throw self::malformed("data.{$member} must be an object.", "/data/{$member}");
            }
        }

        return new self(
            $data['attributes'] ?? [],
            $data['relationships'] ?? [],
            is_array($document['meta'] ?? null) ? $document['meta'] : [],
        );
    }

    /**
     * Validates attributes with Laravel's rules; failures point at
     * /data/attributes/{name}.
     *
     * @param  array<string, mixed>  $rules
     * @return array<string, mixed>
     */
    public function validate(array $rules): array
    {
        $validator = Validator::make(['data' => ['attributes' => $this->attributes]], self::prefix($rules));

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        return $this->attributes;
    }

    public function has(string $attribute): bool
    {
        return array_key_exists($attribute, $this->attributes);
    }

    /**
     * The id in a to-one relationship, or null when it is set to null.
     */
    public function toOne(string $relationship, string $type): ?string
    {
        $data = $this->relationships[$relationship]['data'] ?? null;

        if ($data === null) {
            return null;
        }

        if (! is_array($data) || ($data['type'] ?? null) !== $type || ! is_string($data['id'] ?? null)) {
            throw self::malformed("{$relationship} must be a {$type} resource identifier.", "/data/relationships/{$relationship}");
        }

        return $data['id'];
    }

    /**
     * @return list<string>
     */
    public function toMany(string $relationship, string $type): array
    {
        $data = $this->relationships[$relationship]['data'] ?? [];

        if (! is_array($data) || ! array_is_list($data)) {
            throw self::malformed("{$relationship} must be a list of {$type} resource identifiers.", "/data/relationships/{$relationship}");
        }

        $ids = [];

        foreach ($data as $index => $identifier) {
            if (! is_array($identifier) || ($identifier['type'] ?? null) !== $type || ! is_string($identifier['id'] ?? null)) {
                throw self::malformed("Each {$relationship} entry must be a {$type} resource identifier.", "/data/relationships/{$relationship}/data/{$index}");
            }

            $ids[] = $identifier['id'];
        }

        return $ids;
    }

    public function hasRelationship(string $relationship): bool
    {
        return array_key_exists($relationship, $this->relationships);
    }

    /**
     * @param  array<string, mixed>  $rules
     * @return array<string, mixed>
     */
    private static function prefix(array $rules): array
    {
        $prefixed = [];

        foreach ($rules as $field => $rule) {
            $prefixed["data.attributes.{$field}"] = $rule;
        }

        return $prefixed;
    }

    private static function malformed(string $detail, string $pointer): ApiError
    {
        return new ApiError(ErrorCode::MalformedRequestBody, $detail, source: $pointer === '' ? [] : ['pointer' => $pointer]);
    }
}
