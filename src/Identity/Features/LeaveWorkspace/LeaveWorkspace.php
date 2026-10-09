<?php

declare(strict_types=1);

namespace Longhand\Identity\Features\LeaveWorkspace;

use Longhand\Identity\ActingMember;
use Longhand\Identity\Features\DeactivateMember\EndsMemberships;
use Longhand\Identity\Models\Member;
use Longhand\Shared\Actions\Action;
use Longhand\Shared\Actions\NoInput;
use Longhand\Shared\Actors\AuthorisedActor;
use Longhand\Shared\Events\RecordedEvents;

/**
 * A person leaves a workspace. It needs no scope and agents cannot do it,
 * and the last owner cannot leave until there is another (RFC 0003).
 */
#[Action('member.leave', humansOnly: true, approvable: false)]
final readonly class LeaveWorkspace
{
    use EndsMemberships;

    public function __construct(private RecordedEvents $events) {}

    public function handle(AuthorisedActor $actor, NoInput $payload): Member
    {
        $member = ActingMember::of($actor);

        $this->ensureNotLastOwner($member);
        $this->end($member, 'left', $this->events);

        return $member;
    }
}
