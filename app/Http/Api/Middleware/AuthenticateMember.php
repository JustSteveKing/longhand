<?php

declare(strict_types=1);

namespace App\Http\Api\Middleware;

use App\Exceptions\ErrorCode;
use App\Http\Api\ApiActorResolver;
use App\Http\Api\JsonApi\ApiError;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final readonly class AuthenticateMember
{
    public function __construct(private ApiActorResolver $resolver) {}

    public function handle(Request $request, Closure $next): Response
    {
        $actor = $this->resolver->resolve($request) ?? throw new ApiError(
            ErrorCode::Unauthorized,
            'Send a valid access token as Authorization: Bearer.',
            source: ['header' => 'Authorization'],
            headers: ['WWW-Authenticate' => 'Bearer'],
        );

        $request->attributes->set('actor', $actor);

        return $next($request);
    }
}
