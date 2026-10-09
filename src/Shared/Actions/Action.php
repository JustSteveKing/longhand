<?php

declare(strict_types=1);

namespace Longhand\Shared\Actions;

use Attribute;

/**
 * Names a use case and declares what it needs (ADR 0017).
 *
 * Every Action class carries one. The runner reads it to check the scope,
 * to decide whether an agent's approval rules apply, and to name the
 * action in the audit log and in errors.
 */
#[Attribute(Attribute::TARGET_CLASS)]
final readonly class Action
{
    /**
     * @param  string  $name  The action, as `resource.verb`, such as `thread.resolve`.
     * @param  string|null  $scope  The scope the action needs, or null when it needs none.
     * @param  bool  $approvable  Whether an agent's `requires_approval_for` can apply to it.
     * @param  bool  $humansOnly  Whether agents are refused it whatever their scopes (ADR 0016).
     * @param  bool  $requiresMember  False only for what an account does before it has a member, such as creating a workspace.
     */
    public function __construct(
        public string $name,
        public ?string $scope = null,
        public bool $approvable = true,
        public bool $humansOnly = false,
        public bool $requiresMember = true,
    ) {}
}
