<?php

declare(strict_types=1);

namespace App\Http\Api\V1\Identity;

use App\Exceptions\ErrorCode;
use App\Http\Api\ApiActor;
use App\Http\Api\CurrentMember;
use App\Http\Api\JsonApi\ApiError;
use App\Http\Api\JsonApi\Document;
use App\Http\Api\JsonApi\QueryParameters;
use App\Http\Api\JsonApi\RequestDocument;
use App\Http\Api\JsonApi\Versions;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Longhand\Identity\Enums\MemberKind;
use Longhand\Identity\Enums\MemberStatus;
use Longhand\Identity\Enums\Role;
use Longhand\Identity\Features\ChangeRole\ChangeRole;
use Longhand\Identity\Features\ChangeRole\ChangeRolePayload;
use Longhand\Identity\Features\ChangeRole\MemberPayload;
use Longhand\Identity\Features\ConfigureAgent\AgentPayload;
use Longhand\Identity\Features\ConfigureAgent\ConfigureAgent;
use Longhand\Identity\Features\ConfigureAgent\ConfigureAgentPayload;
use Longhand\Identity\Features\DeactivateMember\DeactivateMember;
use Longhand\Identity\Features\DelegateAgent\DelegateAgent;
use Longhand\Identity\Features\DelegateAgent\DelegateAgentPayload;
use Longhand\Identity\Features\LeaveWorkspace\LeaveWorkspace;
use Longhand\Identity\Features\ReactivateMember\ReactivateMember;
use Longhand\Identity\Features\ResumeAgent\ResumeAgent;
use Longhand\Identity\Features\SuspendAgent\SuspendAgent;
use Longhand\Identity\Features\TransferAgent\TransferAgent;
use Longhand\Identity\Features\TransferAgent\TransferAgentPayload;
use Longhand\Identity\Features\UpdateProfile\UpdateProfile;
use Longhand\Identity\Features\UpdateProfile\UpdateProfilePayload;
use Longhand\Identity\Handle;
use Longhand\Identity\Models\Member;
use Longhand\Shared\Actions\ActionRunner;
use Longhand\Shared\Actions\NoInput;
use Longhand\Shared\Actors\Actor;

/**
 * PATCH /v1/members/{member}: one route, several actions (ADR 0013). Each
 * change becomes its named action, run in order in one transaction, so a
 * document asking for two things gets both or neither.
 */
final readonly class UpdateMemberController
{
    public function __construct(private Document $document, private ActionRunner $runner) {}

    public function __invoke(Request $request, string $member): JsonResponse
    {
        $parameters = QueryParameters::from($request);
        $target = Member::query()->where('workspace_id', CurrentMember::of($request)->workspace_id)->findOrFail($member);
        $input = RequestDocument::from($request, 'members', $target->id);

        Versions::require($request, $target);

        $attributes = $input->validate([
            'status' => ['sometimes', 'in:active,deactivated,suspended'],
            'role' => ['sometimes', 'in:owner,admin,member,guest'],
            'display_name' => ['sometimes', 'string', 'min:1', 'max:120'],
            'handle' => ['sometimes', 'string'],
            'timezone' => ['sometimes', 'timezone:all'],
            'description' => ['sometimes', 'nullable', 'string', 'max:500'],
            'scopes' => ['sometimes', 'array'],
            'scopes.*' => ['string'],
            'requires_approval_for' => ['sometimes', 'array'],
            'requires_approval_for.*' => ['string'],
        ]);

        $actor = ApiActor::of($request);

        $changes = $this->changes($actor, $target, $input, $attributes);

        // Check every change first, outside the transaction, so a refusal is
        // audited; then apply them all or none.
        foreach ($changes as [$action, $payload]) {
            $this->runner->precheck($actor, $action, $payload);
        }

        DB::transaction(function () use ($actor, $changes): void {
            foreach ($changes as [$action, $payload]) {
                $this->runner->run($actor, $action, $payload);
            }
        });

        return $this->document->resource($request, $target->fresh() ?? $target, $parameters);
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return list<array{0: class-string, 1: object}>
     */
    private function changes(Actor $actor, Member $target, RequestDocument $input, array $attributes): array
    {
        $changes = [];
        $agent = $target->kind === MemberKind::Agent;

        if (array_intersect_key($attributes, array_flip(['display_name', 'handle', 'timezone'])) !== [] && ! $agent) {
            if ($target->id !== $actor->memberId) {
                throw new ApiError(ErrorCode::InsufficientScope, 'A person edits only their own profile.', source: ['pointer' => '/data/attributes']);
            }

            $changes[] = [UpdateProfile::class, new UpdateProfilePayload(
                displayName: $attributes['display_name'] ?? null,
                handle: isset($attributes['handle']) ? Handle::from($attributes['handle']) : null,
                timezone: $attributes['timezone'] ?? null,
            )];
        }

        if ($agent && (array_intersect_key($attributes, array_flip(['display_name', 'description', 'scopes', 'requires_approval_for'])) !== [] || $input->hasRelationship('spaces'))) {
            $changes[] = [ConfigureAgent::class, new ConfigureAgentPayload(
                agentId: $target->id,
                scopes: isset($attributes['scopes']) ? array_values($attributes['scopes']) : null,
                requiresApprovalFor: isset($attributes['requires_approval_for']) ? array_values($attributes['requires_approval_for']) : null,
                spaceIds: $input->hasRelationship('spaces') ? $input->toMany('spaces', 'spaces') : null,
                displayName: $attributes['display_name'] ?? null,
                description: $attributes['description'] ?? null,
            )];
        }

        if (isset($attributes['role'])) {
            $changes[] = [ChangeRole::class, new ChangeRolePayload($target->id, Role::from($attributes['role']))];
        }

        if ($agent && $input->hasRelationship('owner')) {
            $owner = $input->toOne('owner', 'members') ?? throw new ApiError(ErrorCode::ValidationFailed, 'An agent always has an owner.', source: ['pointer' => '/data/relationships/owner']);
            $changes[] = [TransferAgent::class, new TransferAgentPayload($target->id, $owner)];
        }

        if ($agent && $input->hasRelationship('acts_on_behalf_of')) {
            $changes[] = [DelegateAgent::class, new DelegateAgentPayload($target->id, $input->toOne('acts_on_behalf_of', 'members') !== null)];
        }

        if (isset($attributes['status']) && $attributes['status'] !== $target->status->value) {
            $changes[] = $this->statusChange($actor, $target, MemberStatus::from($attributes['status']));
        }

        return $changes;
    }

    /**
     * @return array{0: class-string, 1: object}
     */
    private function statusChange(Actor $actor, Member $target, MemberStatus $to): array
    {
        return match (true) {
            $to === MemberStatus::Deactivated && $target->id === $actor->memberId => [LeaveWorkspace::class, new NoInput],
            $to === MemberStatus::Deactivated => [DeactivateMember::class, new MemberPayload($target->id)],
            $target->kind === MemberKind::Agent && $to === MemberStatus::Suspended => [SuspendAgent::class, new AgentPayload($target->id)],
            $target->kind === MemberKind::Agent => [ResumeAgent::class, new AgentPayload($target->id)],
            $to === MemberStatus::Active => [ReactivateMember::class, new MemberPayload($target->id)],
            default => throw new ApiError(ErrorCode::InvalidTransition, 'Only agents are suspended.', source: ['pointer' => '/data/attributes/status'], meta: ['current_state' => $target->status->value, 'allowed' => ['active', 'deactivated']]),
        };
    }
}
