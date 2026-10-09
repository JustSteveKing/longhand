<?php

declare(strict_types=1);

namespace Longhand\Identity\Features\ConfigureAgent;

/**
 * What to change about an agent; null leaves it as it is.
 */
final readonly class ConfigureAgentPayload
{
    /**
     * @param  list<string>|null  $scopes
     * @param  list<string>|null  $requiresApprovalFor
     * @param  list<string>|null  $spaceIds
     * @param  array{provider: string, name: string}|null  $model
     */
    public function __construct(
        public string $agentId,
        public ?array $scopes = null,
        public ?array $requiresApprovalFor = null,
        public ?array $spaceIds = null,
        public ?string $displayName = null,
        public ?string $description = null,
        public ?array $model = null,
    ) {}
}
