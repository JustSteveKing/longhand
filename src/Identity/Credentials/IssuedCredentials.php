<?php

declare(strict_types=1);

namespace Longhand\Identity\Credentials;

use Illuminate\Database\Eloquent\Model;
use Longhand\Identity\Models\Member;
use Longhand\Shared\Actions\HasSubject;

/**
 * An agent with credentials just issued for it, returned once.
 */
final readonly class IssuedCredentials implements HasSubject
{
    public function __construct(
        public Member $agent,
        public ClientCredentials $credentials,
    ) {}

    public function subject(): Model
    {
        return $this->agent;
    }
}
