<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Carbon;
use Longhand\Identity\Audit\AuditEvent;
use Longhand\Identity\Enums\InvitationStatus;
use Longhand\Identity\Enums\Role;
use Longhand\Identity\Events\InvitationAccepted;
use Longhand\Identity\Events\InvitationCreated;
use Longhand\Identity\Events\InvitationRevoked;
use Longhand\Identity\Events\MemberJoined;
use Longhand\Identity\Exceptions\AlreadyAMember;
use Longhand\Identity\Exceptions\InvitationAlreadyPending;
use Longhand\Identity\Exceptions\InvitationNotPending;
use Longhand\Identity\Exceptions\UnverifiedAccount;
use Longhand\Identity\Features\ExpireInvitations\ExpireInvitations;
use Longhand\Identity\Features\InviteMember\InviteMember;
use Longhand\Identity\Features\InviteMember\InviteMemberPayload;
use Longhand\Identity\Features\InviteMember\IssuedInvitation;
use Longhand\Identity\Features\JoinWorkspace\JoinWorkspace;
use Longhand\Identity\Features\JoinWorkspace\JoinWorkspacePayload;
use Longhand\Identity\Features\JoinWorkspace\NewMember;
use Longhand\Identity\Features\ResendInvitation\InvitationPayload;
use Longhand\Identity\Features\ResendInvitation\ResendInvitation;
use Longhand\Identity\Features\RevokeInvitation\RevokeInvitation;
use Longhand\Identity\Handle;
use Longhand\Identity\Models\Invitation;
use Longhand\Identity\Models\Member;
use Longhand\Shared\Actions\ActionLog;
use Longhand\Shared\Actions\ActionRefused;
use Longhand\Shared\Actions\NoInput;
use Longhand\Shared\Actors\Actor;
use Tests\Support\RecordingActionLog;

function invite(Member $inviter, string $email = 'priya@acme.example', Role $role = Role::Member): IssuedInvitation
{
    return runAction(memberActor($inviter), InviteMember::class, new InviteMemberPayload($email, $role));
}

function acceptInvitation(User $account, string $token, string $handle = 'priya'): Member
{
    return runAction(accountActor($account), JoinWorkspace::class, new JoinWorkspacePayload(
        $token,
        new NewMember('Priya Patel', Handle::from($handle), 'Asia/Kolkata'),
    ));
}

beforeEach(function (): void {
    $this->owner = Member::factory()->owner()->create();
});

it('invites by email, for 7 days, keeping only a hash of the token', function (): void {
    Carbon::setTestNow('2026-10-09 10:00:00');

    $issued = invite($this->owner, ' Priya@Acme.example ');

    expect($issued->invitation->id)->toMatch('/^inv_/')
        ->and($issued->invitation->email)->toBe('priya@acme.example')
        ->and($issued->invitation->status)->toBe(InvitationStatus::Pending)
        ->and($issued->invitation->expires_at->toDateTimeString())->toBe('2026-10-16 10:00:00')
        ->and($issued->invitation->fresh()->token_hash)->toBe(Invitation::hashToken($issued->token))
        ->and($issued->invitation->toArray())->not->toHaveKey('token_hash');
});

it('lets admins invite, but only owners invite owners', function (): void {
    $admin = Member::factory()->admin()->create(['workspace_id' => $this->owner->workspace_id]);

    expect(invite($admin, role: Role::Admin)->invitation->role)->toBe(Role::Admin);
    expect(fn () => invite($admin, 'boss@acme.example', Role::Owner))->toThrow(ActionRefused::class, 'Only owners invite owners');
    expect(invite($this->owner, 'boss@acme.example', Role::Owner)->invitation->role)->toBe(Role::Owner);
});

it('refuses members, guests and agents who try to invite', function (): void {
    $member = Member::factory()->create(['workspace_id' => $this->owner->workspace_id]);

    expect(fn () => invite($member))->toThrow(ActionRefused::class);
});

it('refuses to invite someone who is already a member, or already invited', function (): void {
    $priya = User::factory()->create(['email' => 'priya@acme.example']);
    Member::factory()->create(['workspace_id' => $this->owner->workspace_id, 'account_id' => $priya->id]);

    expect(fn () => invite($this->owner, 'PRIYA@acme.example'))->toThrow(AlreadyAMember::class);

    invite($this->owner, 'sam@acme.example');
    expect(fn () => invite($this->owner, 'sam@acme.example'))->toThrow(InvitationAlreadyPending::class);
});

it('joins with the invited role from any verified account, whatever its email', function (): void {
    $log = new RecordingActionLog;
    $this->app->instance(ActionLog::class, $log);
    $issued = invite($this->owner, 'priya@work.example', Role::Admin);
    $personal = User::factory()->create(['email' => 'priya@personal.example']);

    $member = acceptInvitation($personal, $issued->token);

    expect($member->workspace_id)->toBe($this->owner->workspace_id)
        ->and($member->account_id)->toBe($personal->id)
        ->and($member->role)->toBe(Role::Admin)
        ->and($member->timezone)->toBe('Asia/Kolkata')
        ->and($issued->invitation->fresh()->status)->toBe(InvitationStatus::Accepted);

    expect(array_map(fn ($event) => $event::class, $log->events()))->toBe([
        InvitationCreated::class,
        InvitationAccepted::class,
        MemberJoined::class,
    ]);
    expect($log->events()[2]->invitedById)->toBe($this->owner->id);
});

it('cannot be used twice', function (): void {
    $issued = invite($this->owner);
    acceptInvitation(User::factory()->create(), $issued->token);

    expect(fn () => acceptInvitation(User::factory()->create(), $issued->token, 'other'))->toThrow(InvitationNotPending::class, 'accepted');
});

it('needs a verified account to join', function (): void {
    $issued = invite($this->owner);

    expect(fn () => acceptInvitation(User::factory()->unverified()->create(), $issued->token))->toThrow(UnverifiedAccount::class);
});

it('does not admit anyone with an unknown token', function (): void {
    expect(fn () => acceptInvitation(User::factory()->create(), 'not-a-real-token'))->toThrow(ModelNotFoundException::class);
});

it('expires after 7 days, and resending restarts the 7 days with a new link', function (): void {
    Carbon::setTestNow('2026-10-09 10:00:00');
    $issued = invite($this->owner);

    Carbon::setTestNow('2026-10-17 10:00:00');
    expect(runAction(Actor::system(), ExpireInvitations::class, new NoInput))->toBe(1);
    expect(fn () => acceptInvitation(User::factory()->create(), $issued->token))->toThrow(InvitationNotPending::class, 'expired');

    $resent = runAction(memberActor($this->owner), ResendInvitation::class, new InvitationPayload($issued->invitation->id));

    expect($resent->token)->not->toBe($issued->token)
        ->and($resent->invitation->status)->toBe(InvitationStatus::Pending)
        ->and($resent->invitation->expires_at->toDateTimeString())->toBe('2026-10-24 10:00:00');
    expect(acceptInvitation(User::factory()->create(), $resent->token)->exists)->toBeTrue();
});

it('treats a pending invitation past its 7 days as expired before the schedule catches up', function (): void {
    Carbon::setTestNow('2026-10-09 10:00:00');
    $issued = invite($this->owner);
    Carbon::setTestNow('2026-10-16 10:00:01');

    expect(fn () => acceptInvitation(User::factory()->create(), $issued->token))->toThrow(InvitationNotPending::class, 'expired');
});

it('revokes a pending invitation', function (): void {
    $log = new RecordingActionLog;
    $this->app->instance(ActionLog::class, $log);
    $issued = invite($this->owner);

    runAction(memberActor($this->owner), RevokeInvitation::class, new InvitationPayload($issued->invitation->id));

    expect($issued->invitation->fresh()->status)->toBe(InvitationStatus::Revoked)
        ->and(end($log->allowed)['events'][0])->toEqual(new InvitationRevoked($this->owner->workspace_id, $issued->invitation->id, 'revoked'));
    expect(fn () => acceptInvitation(User::factory()->create(), $issued->token))->toThrow(InvitationNotPending::class, 'revoked');
});

it('does not let one workspace see another\'s invitations', function (): void {
    $issued = invite($this->owner);
    $stranger = Member::factory()->owner()->create();

    expect(fn () => runAction(memberActor($stranger), RevokeInvitation::class, new InvitationPayload($issued->invitation->id)))
        ->toThrow(ModelNotFoundException::class);
});

it('audits an invitation with the invitation as its subject', function (): void {
    $issued = invite($this->owner);

    expect(AuditEvent::query()->where('action', 'member.invite')->sole())
        ->subject_type->toBe('invitations')
        ->subject_id->toBe($issued->invitation->id)
        ->workspace_id->toBe($this->owner->workspace_id);
});
