<?php

declare(strict_types=1);

namespace Longhand\Identity\Features\AddEmailDomain;

use Longhand\Identity\ActingMember;
use Longhand\Identity\Exceptions\EmailDomainNotAllowed;
use Longhand\Identity\Models\Account;
use Longhand\Identity\Models\EmailDomain;
use Longhand\Shared\Actions\Action;
use Longhand\Shared\Actions\Guarded;
use Longhand\Shared\Actors\AuthorisedActor;

/**
 * Lets verified addresses at a domain join without an invitation. The
 * owner adding it must have a verified address there themselves, and
 * public mail providers are refused (RFC 0003).
 */
#[Action('workspace.add_email_domain', scope: 'workspace:write', humansOnly: true)]
final readonly class AddEmailDomain implements Guarded
{
    use OwnersOnly;

    public function handle(AuthorisedActor $actor, EmailDomainPayload $payload): EmailDomain
    {
        $owner = ActingMember::of($actor);
        $domain = $payload->normalised();
        $account = Account::query()->find($owner->account_id);

        if (in_array($domain, EmailDomain::PUBLIC_PROVIDERS, true)) {
            throw new EmailDomainNotAllowed("{$domain} is a public mail provider, so anyone could use it to join.");
        }

        if ($account === null || ! $account->isVerified() || $account->emailDomain() !== $domain) {
            throw new EmailDomainNotAllowed("To add {$domain}, your own verified address must be at {$domain}.");
        }

        return EmailDomain::query()->firstOrCreate(
            ['workspace_id' => $owner->workspace_id, 'domain' => $domain],
            ['added_by_id' => $owner->id],
        );
    }
}
