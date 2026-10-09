<?php

declare(strict_types=1);

namespace App\Http\Api\Middleware;

use App\Http\Api\JsonApi\RequestId;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class AssignRequestId
{
    public function handle(Request $request, Closure $next): Response
    {
        $id = RequestId::of($request);
        $response = $next($request);
        $response->headers->set('X-Request-Id', $id);

        return $response;
    }
}
