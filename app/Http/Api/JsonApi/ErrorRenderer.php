<?php

declare(strict_types=1);

namespace App\Http\Api\JsonApi;

use App\Exceptions\ErrorCode;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Longhand\Shared\Actions\ActionRefused;
use Longhand\Shared\Errors\DomainError;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Throwable;

/**
 * Renders anything thrown under /v1 as a JSON:API error document
 * (RFC 0002, ADR 0005). A resource the caller cannot see is always 404,
 * never 403 (ADR 0012).
 */
final class ErrorRenderer
{
    public static function render(Throwable $error, Request $request): JsonResponse
    {
        return match (true) {
            $error instanceof ApiError => self::document($request, [self::object($error->errorCode, $error->getMessage(), $error->source, $error->meta)], $error->errorCode->status(), $error->headers),
            $error instanceof DomainError => self::domain($request, $error),
            $error instanceof ActionRefused => self::refusal($request, $error),
            $error instanceof ValidationException => self::validation($request, $error),
            $error instanceof ModelNotFoundException, $error instanceof NotFoundHttpException => self::single($request, ErrorCode::ResourceNotFound, 'There is nothing here, or it is not visible to you.'),
            $error instanceof MethodNotAllowedHttpException => self::single($request, ErrorCode::MethodNotAllowed, 'This method is not supported here.', $error->getHeaders()),
            $error instanceof ThrottleRequestsException => self::single($request, ErrorCode::RateLimitExceeded, 'Too many requests. Wait and try again.', $error->getHeaders()),
            $error instanceof HttpExceptionInterface && $error->getStatusCode() === 413 => self::single($request, ErrorCode::PayloadTooLarge, 'The request document is over 1 MiB.'),
            $error instanceof HttpExceptionInterface && $error->getStatusCode() === 503 => self::single($request, ErrorCode::ServiceUnavailable, 'Longhand is unavailable for a moment. Try again shortly.', $error->getHeaders()),
            default => self::single($request, ErrorCode::InternalServerError, 'Something went wrong on our side.'),
        };
    }

    private static function domain(Request $request, DomainError $error): JsonResponse
    {
        $code = ErrorCode::tryFrom($error->errorCode()) ?? ErrorCode::InternalServerError;

        return self::document($request, [self::object($code, $error->getMessage(), [], $error->meta())], $code->status());
    }

    private static function refusal(Request $request, ActionRefused $refusal): JsonResponse
    {
        $code = ErrorCode::tryFrom($refusal->errorCode) ?? ErrorCode::InsufficientScope;
        $headers = $code === ErrorCode::InsufficientScope && $refusal->action->scope !== null
            ? ['WWW-Authenticate' => 'Bearer error="insufficient_scope", scope="'.$refusal->action->scope.'"']
            : [];

        return self::document($request, [self::object($code, $refusal->getMessage(), [], ['action' => $refusal->action->name])], $code->status(), $headers);
    }

    private static function validation(Request $request, ValidationException $error): JsonResponse
    {
        $objects = [];

        foreach ($error->errors() as $field => $messages) {
            foreach ($messages as $message) {
                $objects[] = self::object(ErrorCode::ValidationFailed, $message, ['pointer' => '/'.str_replace('.', '/', $field)]);
            }
        }

        return self::document($request, $objects, 422);
    }

    /**
     * @param  array<string, mixed>  $headers
     */
    private static function single(Request $request, ErrorCode $code, string $detail, array $headers = []): JsonResponse
    {
        return self::document($request, [self::object($code, $detail)], $code->status(), $headers);
    }

    /**
     * @param  array<string, string>  $source
     * @param  array<string, mixed>  $meta
     * @return array<string, mixed>
     */
    private static function object(ErrorCode $code, string $detail, array $source = [], array $meta = []): array
    {
        return array_filter([
            'status' => (string) $code->status(),
            'code' => $code->value,
            'title' => $code->title(),
            'detail' => $detail,
            'links' => ['type' => $code->type()],
            'source' => $source === [] ? null : $source,
            'meta' => $meta === [] ? null : $meta,
        ], fn ($value) => $value !== null);
    }

    /**
     * @param  list<array<string, mixed>>  $errors
     * @param  array<string, mixed>  $headers
     */
    private static function document(Request $request, array $errors, int $status, array $headers = []): JsonResponse
    {
        return new JsonResponse(
            ['jsonapi' => ['version' => '1.1'], 'errors' => $errors, 'meta' => ['request_id' => RequestId::of($request)]],
            $status,
            ['Content-Type' => MediaType::JSON_API, 'X-Request-Id' => RequestId::of($request), ...$headers],
            JSON_UNESCAPED_SLASHES,
        );
    }
}
