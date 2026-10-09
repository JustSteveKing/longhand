<?php

declare(strict_types=1);

namespace App\Exceptions;

/**
 * Every error code the API can return, with its status, title and type
 * URL, so no URL is written twice (RFC 0002, RFC 0013).
 *
 * Generic codes link to the apiguide.dev catalogue; codes only Longhand
 * has link to its own problem pages, which are not published until there
 * is a real domain (ADR 0002).
 */
enum ErrorCode: string
{
    case MalformedRequestBody = 'malformed-request-body';
    case InvalidPaginationCursor = 'invalid-pagination-cursor';
    case InvalidQueryParameter = 'invalid-query-parameter';
    case Unauthorized = 'unauthorized';
    case ExpiredAuthenticationToken = 'expired-authentication-token';
    case InsufficientScope = 'insufficient-scope';
    case ResourceNotFound = 'resource-not-found';
    case MethodNotAllowed = 'method-not-allowed';
    case NotAcceptable = 'not-acceptable';
    case IdempotencyKeyConflict = 'idempotency-key-conflict';
    case ResourceConflict = 'resource-conflict';
    case PreconditionFailed = 'precondition-failed';
    case PayloadTooLarge = 'payload-too-large';
    case UnsupportedMediaType = 'unsupported-media-type';
    case ValidationFailed = 'validation-failed';
    case PreconditionRequired = 'precondition-required';
    case RateLimitExceeded = 'rate-limit-exceeded';
    case InternalServerError = 'internal-server-error';
    case ServiceUnavailable = 'service-unavailable';
    case MaxSizeExceeded = 'max-size-exceeded';
    case ApprovalRequired = 'approval-required';
    case InvalidTransition = 'invalid-transition';
    case UncitedContent = 'uncited-content';
    case DecisionImmutable = 'decision-immutable';
    case IncidentOnly = 'incident-only';
    case LastOwner = 'last-owner';
    case ScopeExceedsOwner = 'scope-exceeds-owner';
    case AgentLimitReached = 'agent-limit-reached';
    case ThreadNotOpen = 'thread-not-open';
    case UnsupportedOperations = 'unsupported-operations';
    case NotARespondent = 'not-a-respondent';

    public function status(): int
    {
        return match ($this) {
            self::MalformedRequestBody, self::InvalidPaginationCursor, self::InvalidQueryParameter,
            self::MaxSizeExceeded, self::UnsupportedOperations => 400,
            self::Unauthorized, self::ExpiredAuthenticationToken => 401,
            self::InsufficientScope, self::ApprovalRequired, self::NotARespondent => 403,
            self::ResourceNotFound => 404,
            self::MethodNotAllowed => 405,
            self::NotAcceptable => 406,
            self::IdempotencyKeyConflict, self::ResourceConflict, self::InvalidTransition,
            self::DecisionImmutable, self::LastOwner, self::AgentLimitReached, self::ThreadNotOpen => 409,
            self::PreconditionFailed => 412,
            self::PayloadTooLarge => 413,
            self::UnsupportedMediaType => 415,
            self::ValidationFailed, self::UncitedContent, self::IncidentOnly, self::ScopeExceedsOwner => 422,
            self::PreconditionRequired => 428,
            self::RateLimitExceeded => 429,
            self::InternalServerError => 500,
            self::ServiceUnavailable => 503,
        };
    }

    public function title(): string
    {
        return match ($this) {
            self::MaxSizeExceeded => 'Page Size Too Large',
            default => ucwords(str_replace('-', ' ', $this->value)),
        };
    }

    public function type(): string
    {
        return match (true) {
            $this === self::MaxSizeExceeded => 'https://jsonapi.org/profiles/ethanresnick/cursor-pagination/max-size-exceeded',
            $this->isProductSpecific() => "https://api.longhand.example/problems/{$this->value}",
            default => "https://apiguide.dev/errors/{$this->value}",
        };
    }

    public function isProductSpecific(): bool
    {
        return in_array($this, [
            self::ApprovalRequired, self::InvalidTransition, self::UncitedContent, self::DecisionImmutable,
            self::IncidentOnly, self::LastOwner, self::ScopeExceedsOwner, self::AgentLimitReached,
            self::ThreadNotOpen, self::UnsupportedOperations, self::NotARespondent,
        ], true);
    }
}
