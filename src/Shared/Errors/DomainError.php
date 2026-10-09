<?php

declare(strict_types=1);

namespace Longhand\Shared\Errors;

use RuntimeException;

/**
 * A rule of the domain said no. Each surface renders it with its error
 * code (RFC 0002): a JSON:API error object, an MCP tool error, a form
 * error in the web app.
 */
abstract class DomainError extends RuntimeException
{
    /**
     * The code from RFC 0002's error index, such as `resource-conflict`.
     */
    abstract public function errorCode(): string;

    /**
     * Whatever the client needs to recover.
     *
     * @return array<string, mixed>
     */
    public function meta(): array
    {
        return [];
    }
}
