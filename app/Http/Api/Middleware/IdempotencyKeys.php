<?php

declare(strict_types=1);

namespace App\Http\Api\Middleware;

use App\Exceptions\ErrorCode;
use App\Http\Api\ApiActor;
use App\Http\Api\JsonApi\ApiError;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

/**
 * An `Idempotency-Key` on a POST makes it safe to retry (RFC 0002,
 * ADR 0010). Keys are scoped to the member, method and path and kept 24
 * hours. A replay returns the original response with
 * `Idempotent-Replayed: true`; the same key with a different body, or
 * while the first request is still running, is 409. 5xx responses are not
 * stored, so a server failure can be retried with the same key.
 */
final class IdempotencyKeys
{
    public const int HOURS = 24;

    public function handle(Request $request, Closure $next): Response
    {
        $key = $request->header('Idempotency-Key');

        if ($request->method() !== 'POST' || ! is_string($key) || $key === '') {
            return $next($request);
        }

        if (strlen($key) > 255) {
            throw new ApiError(ErrorCode::ValidationFailed, 'Idempotency-Key is at most 255 characters.', source: ['header' => 'Idempotency-Key']);
        }

        $actor = ApiActor::of($request);
        $identity = [
            'scope' => $actor->memberId ?? 'account:'.$actor->accountId,
            'method' => 'POST',
            'path' => $request->path(),
            'key' => $key,
        ];
        $hash = hash('sha256', $request->getContent());

        DB::table('idempotency_keys')
            ->where('created_at', '<', Carbon::now()->subHours(self::HOURS))
            ->where($identity)
            ->delete();

        // ON CONFLICT DO NOTHING: a key already there means a replay, and
        // PostgreSQL's transaction stays usable, unlike after a caught violation.
        $claimed = DB::table('idempotency_keys')->insertOrIgnore([...$identity, 'request_hash' => $hash, 'created_at' => Carbon::now()]);

        if ($claimed === 0) {
            return $this->replay($identity, $hash);
        }

        $response = $next($request);

        if ($response->getStatusCode() >= 500) {
            DB::table('idempotency_keys')->where($identity)->delete();

            return $response;
        }

        DB::table('idempotency_keys')->where($identity)->update([
            'status' => $response->getStatusCode(),
            'headers' => json_encode($this->replayableHeaders($response), JSON_THROW_ON_ERROR),
            'body' => $response->getContent(),
        ]);

        return $response;
    }

    /**
     * @param  array<string, string>  $identity
     */
    private function replay(array $identity, string $hash): Response
    {
        $stored = DB::table('idempotency_keys')->where($identity)->first();

        if ($stored === null || $stored->request_hash !== $hash) {
            throw new ApiError(
                ErrorCode::IdempotencyKeyConflict,
                'This Idempotency-Key was used with a different request body.',
                source: ['header' => 'Idempotency-Key'],
            );
        }

        if ($stored->status === null) {
            throw new ApiError(
                ErrorCode::IdempotencyKeyConflict,
                'The first request with this Idempotency-Key is still being processed.',
                source: ['header' => 'Idempotency-Key'],
                headers: ['Retry-After' => '1'],
            );
        }

        /** @var array<string, string> $headers */
        $headers = json_decode($stored->headers, true, flags: JSON_THROW_ON_ERROR);

        return new Response($stored->body, $stored->status, [...$headers, 'Idempotent-Replayed' => 'true']);
    }

    /**
     * @return array<string, string>
     */
    private function replayableHeaders(Response $response): array
    {
        $headers = [];

        foreach (['Content-Type', 'Location', 'ETag'] as $name) {
            $value = $response->headers->get($name);

            if ($value !== null) {
                $headers[$name] = $value;
            }
        }

        return $headers;
    }
}
