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
    ) {}

    public static function allow(): self
    {
        return new self(allowed: true, needsApproval: false, reason: null);
    }

    /**
     * The actor may only take the action as a draft for someone to approve (ADR 0018).
     */
    public static function approvalRequired(): self
    {
        return new self(allowed: true, needsApproval: true, reason: null);
    }

    public static function refuse(string $reason): self
    {
        return new self(allowed: false, needsApproval: false, reason: $reason);
    }
}
