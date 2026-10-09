<?php

declare(strict_types=1);

namespace App\Http\Api\Middleware;

use App\Http\Api\ApiActor;
use Closure;
use Illuminate\Cache\RateLimiter;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Limits per member and token, reported with the IETF RateLimit and
 * RateLimit-Policy headers (RFC 0002).
 */
final readonly class RateLimitHeaders
{
    public const int LIMIT = 600;

    public const int WINDOW = 60;

    public function __construct(private RateLimiter $limiter) {}

    public function handle(Request $request, Closure $next): Response
    {
        $actor = ApiActor::of($request);
        $key = 'api:'.($actor->memberId ?? $request->ip());
        $policy = sprintf('"default";q=%d;w=%d', self::LIMIT, self::WINDOW);

        if ($this->limiter->tooManyAttempts($key, self::LIMIT)) {
            $retry = $this->limiter->availableIn($key);

            throw new ThrottleRequestsException('Too many requests.', headers: [
                'Retry-After' => $retry,
                'RateLimit-Policy' => $policy,
                'RateLimit' => sprintf('"default";r=0;t=%d', $retry),
            ]);
        }

        $this->limiter->hit($key, self::WINDOW);
        $response = $next($request);

        $response->headers->set('RateLimit-Policy', $policy);
        $response->headers->set('RateLimit', sprintf(
            '"default";r=%d;t=%d',
            $this->limiter->remaining($key, self::LIMIT),
            $this->limiter->availableIn($key),
        ));

        return $response;
    }
}
