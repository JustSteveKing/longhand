<?php

declare(strict_types=1);

namespace App\Auth;

use Illuminate\Auth\GuardHelpers;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Auth\StatefulGuard;
use Illuminate\Contracts\Session\Session;
use LogicException;
use Longhand\Identity\Enums\MemberKind;
use Longhand\Identity\Enums\MemberStatus;
use Longhand\Identity\Models\Member;

/**
 * The signed-in account's member in the workspace it is using, for
 * Passport's authorisation screen (ADR 0060). A token approved there
 * belongs to that member, so it is bound to one workspace (ADR 0015).
 *
 * The workspace is the one chosen in the web app's switcher, kept in the
 * session; without a choice, the account's first active membership.
 */
final class WorkspaceMemberGuard implements StatefulGuard
{
    use GuardHelpers;

    public const string SESSION_KEY = 'workspace_id';

    public function __construct(
        private readonly StatefulGuard $web,
        private readonly Session $session,
    ) {}

    public function user(): ?Authenticatable
    {
        if ($this->user !== null) {
            return $this->user;
        }

        $account = $this->web->user();

        if ($account === null) {
            return null;
        }

        $member = Member::query()
            ->where('account_id', $account->getAuthIdentifier())
            ->where('kind', MemberKind::Human)
            ->where('status', MemberStatus::Active)
            ->when($this->session->get(self::SESSION_KEY), fn ($query, $workspaceId) => $query->where('workspace_id', $workspaceId))
            ->oldest()
            ->first();

        return $this->user = $member === null ? null : TokenHolder::query()->find($member->id);
    }

    /**
     * @param  array<string, mixed>  $credentials
     */
    public function validate(array $credentials = []): bool
    {
        return false;
    }

    /**
     * Signing in happens through the web guard; this guard only reads who
     * that is in a workspace.
     *
     * @param  array<string, mixed>  $credentials
     */
    public function attempt(array $credentials = [], $remember = false): bool
    {
        throw new LogicException('Sign in through the web guard.');
    }

    /**
     * @param  array<string, mixed>  $credentials
     */
    public function once(array $credentials = []): bool
    {
        throw new LogicException('Sign in through the web guard.');
    }

    public function login(Authenticatable $user, $remember = false): void
    {
        throw new LogicException('Sign in through the web guard.');
    }

    public function loginUsingId($id, $remember = false): Authenticatable|false
    {
        throw new LogicException('Sign in through the web guard.');
    }

    public function onceUsingId($id): Authenticatable|false
    {
        throw new LogicException('Sign in through the web guard.');
    }

    public function viaRemember(): bool
    {
        return false;
    }

    /**
     * Passport signs the account out when a client asks for `prompt=login`.
     */
    public function logout(): void
    {
        $this->user = null;
        $this->web->logout();
    }
}
