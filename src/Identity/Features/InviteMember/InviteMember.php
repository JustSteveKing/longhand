<?php

declare(strict_types=1);

namespace Longhand\Identity\Features\InviteMember;

use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Longhand\Identity\ActingMember;
use Longhand\Identity\Enums\InvitationStatus;
use Longhand\Identity\Enums\MemberStatus;
use Longhand\Identity\Enums\Role;
use Longhand\Identity\Events\InvitationCreated;
use Longhand\Identity\Exceptions\AlreadyAMember;
use Longhand\Identity\Exceptions\InvitationAlreadyPending;
use Longhand\Identity\Models\Invitation;
use Longhand\Identity\Models\Member;
use Longhand\Shared\Actions\Action;
use Longhand\Shared\Actions\Authorisation;
use Longhand\Shared\Actions\Guarded;
use Longhand\Shared\Actors\AuthorisedActor;
use Longhand\Shared\Events\RecordedEvents;

/**
 * Owners and admins invite by email, choosing the role it grants. Only
 * owners invite owners (RFC 0003).
 */
#[Action('member.invite', scope: 'members:write', humansOnly: true)]
final readonly class InviteMember implements Guarded
{
    public function __construct(private RecordedEvents $events) {}

    public function guard(AuthorisedActor $actor, object $payload): Authorisation
    {
        $inviter = ActingMember::of($actor);

        return match (true) {
            ! in_array($inviter->role, [Role::Owner, Role::Admin], true) => Authorisation::refuse('Only owners and admins invite people.'),
            $payload instanceof InviteMemberPayload && $payload->role === Role::Owner && $inviter->role !== Role::Owner => Authorisation::refuse('Only owners invite owners.'),
            default => Authorisation::allow(),
        };
    }

    public function handle(AuthorisedActor $actor, InviteMemberPayload $payload): IssuedInvitation
    {
        $inviter = ActingMember::of($actor);
        $email = mb_strtolower(trim($payload->email));

        $isMember = Member::query()
            ->where('workspace_id', $inviter->workspace_id)
            ->where('status', '!=', MemberStatus::Deactivated)
            ->whereIn('account_id', fn ($query) => $query->select('id')->from('users')->whereRaw('lower(email) = ?', [$email]))
            ->exists();

        if ($isMember) {
            throw new AlreadyAMember;
        }

        $pending = Invitation::query()
            ->where('workspace_id', $inviter->workspace_id)
            ->where('email', $email)
            ->where('status', InvitationStatus::Pending)
            ->where('expires_at', '>', Carbon::now())
            ->first();

        if ($pending !== null) {
            throw new InvitationAlreadyPending($pending->id);
        }

        $token = Str::random(48);

        $invitation = Invitation::query()->create([
            'workspace_id' => $inviter->workspace_id,
            'email' => $email,
            'role' => $payload->role,
            'status' => InvitationStatus::Pending,
            'token_hash' => Invitation::hashToken($token),
            'space_ids' => $payload->role === Role::Guest ? $payload->spaceIds : [],
            'invited_by_id' => $inviter->id,
            'expires_at' => Carbon::now()->addDays(Invitation::VALID_FOR_DAYS),
        ]);

        $this->events->record(new InvitationCreated($invitation->workspace_id, $invitation->id, $inviter->id));

        return new IssuedInvitation($invitation, $token);
    }
}
