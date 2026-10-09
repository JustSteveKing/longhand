<?php

declare(strict_types=1);

namespace Longhand\Shared\Actors;

/**
 * Who is asking, before anything has been checked.
 *
 * Each surface builds one from its own credentials: the session for the
 * web app, the token for REST and MCP, nothing for scheduled work. An
 * account with no member yet, during onboarding, has an accountId and no
 * memberId (RFC 0003).
 */
final readonly class Actor
{
    /**
     * @param  list<string>  $scopes  The scopes of the token or session.
     */
    public function __construct(
        public ?string $memberId,
        public Surface $surface,
        public array $scopes = [],
        public ?string $onBehalfOf = null,
        public ?int $accountId = null,
    ) {}

    public static function system(): self
    {
        return new self(memberId: null, surface: Surface::System);
    }

    public function hasScope(string $scope): bool
    {
        return in_array($scope, $this->scopes, true);
    }
}
