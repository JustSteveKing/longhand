<?php

declare(strict_types=1);

namespace App\Http\Api\Middleware;

use App\Exceptions\ErrorCode;
use App\Http\Api\JsonApi\ApiError;
use App\Http\Api\JsonApi\MediaType;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * JSON:API content negotiation (RFC 0002): a body must be
 * application/vnd.api+json with no parameters but `ext` and `profile`,
 * and an extension only where the route supports it (415); Accept must
 * allow a JSON:API media type it has not disqualified (406).
 *
 * Use as `jsonapi` or, where the Atomic Operations extension is
 * supported, `jsonapi:atomic`.
 */
final class NegotiateJsonApi
{
    public function handle(Request $request, Closure $next, string ...$extensions): Response
    {
        $supported = in_array('atomic', $extensions, true) ? [MediaType::ATOMIC] : [];

        // Only methods that carry a body have one to check.
        if (in_array($request->method(), ['POST', 'PATCH', 'PUT', 'DELETE'], true) && $request->getContent() !== '') {
            $this->checkContentType($request->header('Content-Type'), $supported);
        }

        $this->checkAccept($request->header('Accept'), $supported);

        return $next($request);
    }

    /**
     * @param  list<string>  $supported
     */
    private function checkContentType(?string $header, array $supported): void
    {
        [$type, $parameters] = $this->parse($header ?? '');

        if ($type !== MediaType::JSON_API || ! $this->acceptable($parameters, $supported)) {
            throw new ApiError(
                ErrorCode::UnsupportedMediaType,
                'Send the body as '.MediaType::JSON_API.', with no media type parameters other than a supported ext or profile.',
                source: ['header' => 'Content-Type'],
            );
        }
    }

    /**
     * @param  list<string>  $supported
     */
    private function checkAccept(?string $header, array $supported): void
    {
        if ($header === null || trim($header) === '') {
            return;
        }

        $wildcard = false;

        foreach (explode(',', $header) as $entry) {
            [$type, $parameters] = $this->parse($entry);

            if ($type === MediaType::JSON_API && $this->acceptable($parameters, $supported)) {
                return;
            }

            $wildcard = $wildcard || in_array($type, ['*/*', 'application/*'], true);
        }

        $hasJsonApi = str_contains($header, MediaType::JSON_API);

        if ($hasJsonApi || ! $wildcard) {
            throw new ApiError(
                ErrorCode::NotAcceptable,
                'Accept must allow '.MediaType::JSON_API.' without unsupported parameters.',
                source: ['header' => 'Accept'],
            );
        }
    }

    /**
     * @param  array<string, string>  $parameters
     * @param  list<string>  $supported
     */
    private function acceptable(array $parameters, array $supported): bool
    {
        foreach ($parameters as $name => $value) {
            if ($name === 'profile') {
                continue;
            }

            if ($name !== 'ext') {
                return false;
            }

            foreach (array_filter(explode(' ', $value)) as $extension) {
                if (! in_array($extension, $supported, true)) {
                    return false;
                }
            }
        }

        return true;
    }

    /**
     * @return array{0: string, 1: array<string, string>}
     */
    private function parse(string $value): array
    {
        $parts = array_map('trim', explode(';', $value));
        $type = strtolower(array_shift($parts));
        $parameters = [];

        foreach ($parts as $part) {
            if (str_contains($part, '=')) {
                [$name, $parameter] = explode('=', $part, 2);
                $name = strtolower(trim($name));

                if ($name !== 'q') {
                    $parameters[$name] = trim($parameter, ' "');
                }
            }
        }

        return [$type, $parameters];
    }
}
