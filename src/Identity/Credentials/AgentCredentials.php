<?php

declare(strict_types=1);

namespace Longhand\Identity\Credentials;

use Longhand\Identity\Models\Member;

/**
 * Issues an agent's OAuth client credentials (ADR 0060). The application
 * implements it with its OAuth server; Identity only says when.
 */
interface AgentCredentials
{
    public function issue(Member $agent): ClientCredentials;

    /**
     * A new secret, revoking the agent's tokens issued with the old one.
     */
    public function rotate(Member $agent): ClientCredentials;
}
