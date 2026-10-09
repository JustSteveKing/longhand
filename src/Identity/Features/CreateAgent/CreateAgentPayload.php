<?php

declare(strict_types=1);

namespace Longhand\Identity\Features\CreateAgent;

use Longhand\Identity\Handle;

final readonly class CreateAgentPayload
{
    /**
     * @param  list<string>  $scopes
     * @param  list<string>  $requiresApprovalFor  Action names, such as `post.publish` (ADR 0018).
     * @param  list<string>  $spaceIds
     * @param  array{provider: string, name: string}|null  $model
     * @param  string|null  $ownerId  Admins may create an agent for another member; null means the caller.
     */
    public function __construct(
        public string $displayName,
        public Handle $handle,
        public array $scopes = [],
        public array $requiresApprovalFor = [],
        public array $spaceIds = [],
        public ?string $description = null,
        public ?array $model = null,
        public ?string $ownerId = null,
        public ?string $timezone = null,
    ) {}
}
