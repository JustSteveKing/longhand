<?php

declare(strict_types=1);

namespace Longhand\Shared\Actions;

/**
 * The outcome of checking an action.
 */
final readonly class Authorisation
{
    private function __construct(
        public bool $allowed,
        public bool $needsApproval,
        public ?string $reason,
        public ?string $code,
    ) {}

    public static function allow(): self
    {
        return new self(allowed: true, needsApproval: false, reason: null, code: null);
    }

    /**
     * The actor may only take the action as a draft for someone to approve (ADR 0018).
     */
    public static function approvalRequired(): self
    {
        return new self(allowed: true, needsApproval: true, reason: null, code: null);
    }

    /**
     * @param  string  $code  The error code a surface renders it as (RFC 0002), such as `insufficient-scope`.
     */
    public static function refuse(string $reason, string $code = 'insufficient-scope'): self
    {
        return new self(allowed: false, needsApproval: false, reason: $reason, code: $code);
    }
}
