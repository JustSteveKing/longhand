<?php

declare(strict_types=1);

namespace Longhand\Identity\Features\AddEmailDomain;

final readonly class EmailDomainPayload
{
    public function __construct(public string $domain) {}

    public function normalised(): string
    {
        return mb_strtolower(trim($this->domain));
    }
}
