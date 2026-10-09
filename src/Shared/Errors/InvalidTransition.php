<?php

declare(strict_types=1);

namespace Longhand\Shared\Errors;

/**
 * A state change that is not allowed from the current state (RFC 0002).
 */
final class InvalidTransition extends DomainError
{
    /**
     * @param  list<string>  $allowed
     */
    public function __construct(
        string $message,
        public readonly string $currentState,
        public readonly array $allowed = [],
    ) {
        parent::__construct($message);
    }

    public function errorCode(): string
    {
        return 'invalid-transition';
    }

    public function meta(): array
    {
        return ['current_state' => $this->currentState, 'allowed' => $this->allowed];
    }
}
