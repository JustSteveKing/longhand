<?php

declare(strict_types=1);

namespace Longhand\Identity;

use Longhand\Identity\Exceptions\InvalidHandle;
use Stringable;

/**
 * A workspace's or a member's handle: lowercase letters, digits and
 * hyphens, 2 to 32 characters (RFC 0003).
 */
final readonly class Handle implements Stringable
{
    private function __construct(public string $value) {}

    public static function from(string $value): self
    {
        if (preg_match('/^[a-z0-9-]{2,32}$/', $value) !== 1) {
            throw new InvalidHandle($value);
        }

        return new self($value);
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
