<?php

declare(strict_types=1);

namespace App\Http\Api\JsonApi;

final class MediaType
{
    public const string JSON_API = 'application/vnd.api+json';

    public const string ATOMIC = 'https://jsonapi.org/ext/atomic';

    public const string CURSOR_PAGINATION = 'https://jsonapi.org/profiles/ethanresnick/cursor-pagination';
}
