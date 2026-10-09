<?php

declare(strict_types=1);

namespace Longhand\Identity\Credentials;

/**
 * Revokes members' OAuth tokens when their membership ends (RFC 0003).
 */
interface AccessTokens
{
    /**
     * @param  list<string>  $memberIds
     */
    public function revokeFor(array $memberIds): void;
}
