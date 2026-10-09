<?php

declare(strict_types=1);

namespace App\Http\Api\JsonApi;

use App\Exceptions\ErrorCode;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Pagination\Cursor;

/**
 * The JSON:API cursor pagination profile over Laravel's cursor pagination
 * (RFC 0002, ADR 0008). A cursor is opaque and bound to the filters and
 * sort that produced it; reusing it with others is 400.
 */
final readonly class CursorPage
{
    /**
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     * @param  array<string, string>  $columns  Sort fields mapped to their columns.
     * @return CursorPaginator<int, TModel>
     */
    public static function of(Builder $query, QueryParameters $parameters, array $columns, string $keyColumn): CursorPaginator
    {
        if ($parameters->after !== null && $parameters->before !== null) {
            throw new ApiError(ErrorCode::InvalidQueryParameter, 'Use page[after] or page[before], not both.', source: ['parameter' => 'page[before]']);
        }

        $direction = 'asc';

        foreach ($parameters->sorts as $sort) {
            $direction = str_starts_with($sort, '-') ? 'desc' : 'asc';
            $query->orderBy($columns[ltrim($sort, '-')], $direction);
        }

        // The ULID is always the last key, so equal values never swap between pages.
        $query->orderBy($keyColumn, $direction);

        return $query->cursorPaginate(
            $parameters->pageSize,
            ['*'],
            'cursor',
            self::decode($parameters, $parameters->after ?? $parameters->before, $parameters->before !== null),
        );
    }

    /**
     * @template TModel of Model
     *
     * @param  CursorPaginator<int, TModel>  $page
     * @return array{self: string, next: string|null, prev: string|null}
     */
    public static function links(CursorPaginator $page, Request $request, QueryParameters $parameters): array
    {
        $query = $request->query();
        unset($query['page']['after'], $query['page']['before']);

        $link = function (?Cursor $cursor, string $direction) use ($query, $request, $parameters): ?string {
            if ($cursor === null) {
                return null;
            }

            $query['page'][$direction] = self::encode($parameters, $cursor);

            return $request->url().'?'.http_build_query($query);
        };

        return [
            'self' => $request->fullUrl(),
            'next' => $link($page->nextCursor(), 'after'),
            'prev' => $link($page->previousCursor(), 'before'),
        ];
    }

    private static function encode(QueryParameters $parameters, Cursor $cursor): string
    {
        return rtrim(strtr(base64_encode(json_encode([
            'c' => $cursor->encode(),
            'q' => self::fingerprint($parameters),
        ], JSON_THROW_ON_ERROR)), '+/', '-_'), '=');
    }

    private static function decode(QueryParameters $parameters, ?string $value, bool $before): ?Cursor
    {
        if ($value === null) {
            return null;
        }

        $raw = base64_decode(strtr($value, '-_', '+/'), true);
        $decoded = $raw === false ? null : json_decode($raw, true);
        $cursor = is_array($decoded) && ($decoded['q'] ?? null) === self::fingerprint($parameters)
            ? Cursor::fromEncoded($decoded['c'] ?? null)
            : null;

        if ($cursor === null || $cursor->pointsToPreviousItems() !== $before) {
            throw new ApiError(
                ErrorCode::InvalidPaginationCursor,
                'This cursor is unknown, or from a different query.',
                source: ['parameter' => $before ? 'page[before]' : 'page[after]'],
            );
        }

        return $cursor;
    }

    private static function fingerprint(QueryParameters $parameters): string
    {
        return substr(hash('sha256', json_encode([$parameters->filters, $parameters->sorts], JSON_THROW_ON_ERROR)), 0, 16);
    }
}
