<?php

declare(strict_types=1);

namespace App\Http\Api\JsonApi;

use App\Exceptions\ErrorCode;
use Illuminate\Http\Request;

/**
 * The query parameters one endpoint accepts, checked strictly: anything
 * else, including sparse fieldsets, is 400 (RFC 0002, ADR 0009).
 */
final readonly class QueryParameters
{
    public const int DEFAULT_PAGE_SIZE = 50;

    public const int MAX_PAGE_SIZE = 200;

    /**
     * @param  list<string>  $includes
     * @param  array<string, string>  $filters
     * @param  list<string>  $sorts
     */
    private function __construct(
        public array $includes,
        public array $filters,
        public array $sorts,
        public int $pageSize,
        public ?string $after,
        public ?string $before,
    ) {}

    /**
     * @param  list<string>  $includes  Allowed include paths.
     * @param  list<string>  $filters  Allowed filter names.
     * @param  list<string>  $sorts  Allowed sort fields, without a leading `-`.
     * @param  list<string>  $defaultSort
     */
    public static function from(
        Request $request,
        array $includes = [],
        array $filters = [],
        array $sorts = [],
        array $defaultSort = [],
        bool $paginated = false,
    ): self {
        $allowed = ['include', 'filter', 'sort', ...($paginated ? ['page'] : [])];

        foreach (array_keys($request->query()) as $name) {
            if (! in_array($name, $allowed, true)) {
                throw self::invalid("{$name} is not a query parameter of this endpoint.", (string) $name);
            }
        }

        $filterValues = [];

        foreach (self::map($request->query('filter'), 'filter') as $name => $value) {
            if (! in_array($name, $filters, true) || ! is_string($value)) {
                throw self::invalid("filter[{$name}] is not a filter of this endpoint.", "filter[{$name}]");
            }

            $filterValues[$name] = $value;
        }

        $includePaths = self::list($request->query('include'), 'include');

        foreach ($includePaths as $path) {
            if (! in_array($path, $includes, true)) {
                throw self::invalid("{$path} cannot be included here.", 'include');
            }
        }

        $sortFields = self::list($request->query('sort'), 'sort');

        foreach ($sortFields as $field) {
            if (! in_array(ltrim($field, '-'), $sorts, true)) {
                throw self::invalid("{$field} is not a sort of this endpoint.", 'sort');
            }
        }

        $page = self::map($request->query('page'), 'page');

        foreach (array_keys($page) as $name) {
            if (! in_array($name, ['size', 'after', 'before'], true)) {
                throw self::invalid("page[{$name}] is not a pagination parameter.", "page[{$name}]");
            }
        }

        $size = $page['size'] ?? self::DEFAULT_PAGE_SIZE;

        if (! is_numeric($size) || (int) $size < 1) {
            throw self::invalid('page[size] must be a positive whole number.', 'page[size]');
        }

        if ((int) $size > self::MAX_PAGE_SIZE) {
            throw new ApiError(
                ErrorCode::MaxSizeExceeded,
                'page[size] is at most '.self::MAX_PAGE_SIZE.'.',
                source: ['parameter' => 'page[size]'],
                meta: ['page' => ['maxSize' => self::MAX_PAGE_SIZE]],
            );
        }

        return new self(
            includes: $includePaths,
            filters: $filterValues,
            sorts: $sortFields === [] ? $defaultSort : $sortFields,
            pageSize: (int) $size,
            after: is_string($page['after'] ?? null) ? $page['after'] : null,
            before: is_string($page['before'] ?? null) ? $page['before'] : null,
        );
    }

    /**
     * A comma-separated filter's values.
     *
     * @return list<string>|null
     */
    public function filterList(string $name): ?array
    {
        return isset($this->filters[$name]) ? array_values(array_filter(explode(',', $this->filters[$name]))) : null;
    }

    /**
     * @return array<string, mixed>
     */
    private static function map(mixed $value, string $name): array
    {
        if ($value === null) {
            return [];
        }

        if (! is_array($value) || array_is_list($value)) {
            throw self::invalid("{$name} takes named members, such as {$name}[name].", $name);
        }

        return $value;
    }

    /**
     * @return list<string>
     */
    private static function list(mixed $value, string $name): array
    {
        if ($value === null || $value === '') {
            return [];
        }

        if (! is_string($value)) {
            throw self::invalid("{$name} is a comma-separated list.", $name);
        }

        return array_values(array_filter(explode(',', $value)));
    }

    private static function invalid(string $detail, string $parameter): ApiError
    {
        return new ApiError(ErrorCode::InvalidQueryParameter, $detail, source: ['parameter' => $parameter]);
    }
}
