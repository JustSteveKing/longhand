<?php

declare(strict_types=1);

namespace Longhand\Identity\Credentials;

/**
 * An agent's OAuth client credentials. The secret exists only here, once,
 * to be shown to the person who created or rotated it (RFC 0003).
 */
final readonly class ClientCredentials
{
    public function __construct(
        public string $clientId,
        #[\SensitiveParameter] public string $clientSecret,
    ) {}
}
