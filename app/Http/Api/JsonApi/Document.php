<?php

declare(strict_types=1);

namespace App\Http\Api\JsonApi;

use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/**
 * Builds JSON:API response documents: the jsonapi member, primary data,
 * included resources and meta.request_id, under the right media type
 * (RFC 0002).
 */
final readonly class Document
{
    public function __construct(private Serializers $serializers) {}

    /**
     * @param  array<string, string>  $headers
     * @param  array<string, mixed>  $meta
     */
    public function resource(Request $request, Model $model, QueryParameters $parameters, int $status = 200, array $headers = [], array $meta = []): JsonResponse
    {
        return $this->respond($request, [
            'data' => $this->resourceArray($model, $request),
            'included' => $this->included(collect([$model]), $parameters, $request),
        ], $status, [...$headers, ...self::etagHeader($model)], $meta);
    }

    /**
     * @template TModel of Model
     *
     * @param  CursorPaginator<int, TModel>  $page
     */
    public function collection(Request $request, CursorPaginator $page, QueryParameters $parameters): JsonResponse
    {
        /** @var Collection<int, Model> $models */
        $models = collect($page->items());

        return $this->respond($request, [
            'data' => $models->map(fn (Model $model) => $this->resourceArray($model, $request))->values()->all(),
            'included' => $this->included($models, $parameters, $request),
            'links' => CursorPage::links($page, $request, $parameters),
        ], 200, profile: true);
    }

    /**
     * @param  array<string, mixed>  $document
     * @param  array<string, string>  $headers
     * @param  array<string, mixed>  $meta
     */
    public function respond(Request $request, array $document, int $status = 200, array $headers = [], array $meta = [], bool $profile = false): JsonResponse
    {
        if (($document['included'] ?? null) === []) {
            unset($document['included']);
        }

        return new JsonResponse(
            ['jsonapi' => ['version' => '1.1'], ...$document, 'meta' => [...$meta, 'request_id' => RequestId::of($request)]],
            $status,
            ['Content-Type' => MediaType::JSON_API.($profile ? '; profile="'.MediaType::CURSOR_PAGINATION.'"' : ''), ...$headers],
            JSON_UNESCAPED_SLASHES,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function resourceArray(Model $model, Request $request): array
    {
        $serializer = $this->serializers->for($model);

        return $serializer->serialize($model, $request)->toArray(url('/v1/'.$serializer->path($model)));
    }

    /**
     * @return array<string, string>
     */
    public static function etagHeader(Model $model): array
    {
        return ['ETag' => Versions::etag($model)];
    }

    /**
     * Included resources, each once, through the serializer's own loaders,
     * which only return what the caller can see (ADR 0012).
     *
     * @param  Collection<int, Model>  $models
     * @return list<array<string, mixed>>
     */
    private function included(Collection $models, QueryParameters $parameters, Request $request): array
    {
        if ($models->isEmpty() || $parameters->includes === []) {
            return [];
        }

        $serializer = $this->serializers->for($models->first());
        $loaders = $serializer->includes();
        $included = [];

        foreach ($parameters->includes as $path) {
            foreach ($loaders[$path]($models, $request) as $related) {
                $resource = $this->resourceArray($related, $request);
                $included[$resource['type'].':'.$resource['id']] = $resource;
            }
        }

        return array_values($included);
    }
}
