<?php

declare(strict_types=1);

namespace Longhand\Identity\Features\CreateWorkspace;

use LogicException;
use Longhand\Identity\Enums\MemberKind;
use Longhand\Identity\Enums\MemberStatus;
use Longhand\Identity\Enums\Role;
use Longhand\Identity\Events\MemberJoined;
use Longhand\Identity\Events\WorkspaceCreated;
use Longhand\Identity\Exceptions\HandleTaken;
use Longhand\Identity\Models\Member;
use Longhand\Identity\Models\Workspace;
use Longhand\Shared\Actions\Action;
use Longhand\Shared\Actors\AuthorisedActor;
use Longhand\Shared\Events\RecordedEvents;

/**
 * A verified account creates a workspace and becomes its first member,
 * as its owner (RFC 0003). Web app only.
 */
#[Action('workspace.create', approvable: false)]
final readonly class CreateWorkspace
{
    public function __construct(private RecordedEvents $events) {}

    public function handle(AuthorisedActor $actor, CreateWorkspacePayload $payload): Member
    {
        $accountId = $actor->actor->accountId
            ?? throw new LogicException('Only an account can create a workspace.');

        if (Workspace::query()->where('handle', $payload->handle->value)->exists()) {
            throw new HandleTaken($payload->handle->value);
        }

        $workspace = Workspace::query()->create([
            'name' => $payload->name,
            'handle' => $payload->handle->value,
            'default_timezone' => $payload->timezone,
        ]);

        $owner = $workspace->members()->create([
            'account_id' => $accountId,
            'kind' => MemberKind::Human,
            'display_name' => $payload->displayName,
            'handle' => $payload->memberHandle->value,
            'role' => Role::Owner,
            'status' => MemberStatus::Active,
            'timezone' => $payload->timezone,
        ]);

        $this->events->record(new WorkspaceCreated($workspace->id, $owner->id));
        $this->events->record(new MemberJoined($workspace->id, $owner->id));

        return $owner;
    }
}
