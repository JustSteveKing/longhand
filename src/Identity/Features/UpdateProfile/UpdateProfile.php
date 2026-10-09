<?php

declare(strict_types=1);

namespace Longhand\Identity\Features\UpdateProfile;

use Longhand\Identity\ActingMember;
use Longhand\Identity\Events\MemberUpdated;
use Longhand\Identity\Exceptions\HandleTaken;
use Longhand\Identity\Models\Member;
use Longhand\Shared\Actions\Action;
use Longhand\Shared\Actors\AuthorisedActor;
use Longhand\Shared\Events\RecordedEvents;

/**
 * A person edits their own profile in a workspace. It needs no scope, and
 * agents cannot do it (RFC 0003).
 */
#[Action('member.update_profile', humansOnly: true, approvable: false)]
final readonly class UpdateProfile
{
    public function __construct(private RecordedEvents $events) {}

    public function handle(AuthorisedActor $actor, UpdateProfilePayload $payload): Member
    {
        $member = ActingMember::of($actor);

        if ($payload->handle !== null && $payload->handle->value !== $member->handle) {
            $taken = Member::query()
                ->where('workspace_id', $member->workspace_id)
                ->where('handle', $payload->handle->value)
                ->exists();

            if ($taken) {
                throw new HandleTaken($payload->handle->value);
            }
        }

        $member->fill(array_filter([
            'display_name' => $payload->displayName,
            'handle' => $payload->handle?->value,
            'timezone' => $payload->timezone,
        ], fn ($value) => $value !== null));

        $previous = array_intersect_key($member->getOriginal(), $member->getDirty());
        $member->save();

        if ($previous !== []) {
            $this->events->record(new MemberUpdated($member->workspace_id, $member->id, $previous));
        }

        return $member;
    }
}
