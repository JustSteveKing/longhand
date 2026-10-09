<?php

declare(strict_types=1);

namespace Longhand\Identity\Features\UpdateWorkspace;

use Longhand\Identity\Handle;

/**
 * What to change about the workspace; null leaves it as it is.
 */
final readonly class UpdateWorkspacePayload
{
    public function __construct(
        public ?string $name = null,
        public ?Handle $handle = null,
        public ?string $defaultTimezone = null,
        public ?int $maxAgentsPerMember = null,
        public ?int $maxAssistantsPerMember = null,
        public ?int $briefDailyLimit = null,
        public ?bool $semanticSearch = null,
        public ?bool $subscriptionApproval = null,
    ) {}
}
