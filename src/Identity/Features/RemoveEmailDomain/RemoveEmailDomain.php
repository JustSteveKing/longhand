<?php

declare(strict_types=1);

namespace Longhand\Identity\Features\RemoveEmailDomain;

use Longhand\Identity\ActingMember;
use Longhand\Identity\Features\AddEmailDomain\EmailDomainPayload;
use Longhand\Identity\Features\AddEmailDomain\OwnersOnly;
use Longhand\Identity\Models\EmailDomain;
use Longhand\Shared\Actions\Action;
use Longhand\Shared\Actions\Guarded;
use Longhand\Shared\Actors\AuthorisedActor;

/**
 * Stops new people joining through a domain. Members who already joined
 * through it stay (RFC 0003).
 */
#[Action('workspace.remove_email_domain', scope: 'workspace:write', humansOnly: true)]
final readonly class RemoveEmailDomain implements Guarded
{
    use OwnersOnly;

    public function handle(AuthorisedActor $actor, EmailDomainPayload $payload): EmailDomain
    {
        $domain = EmailDomain::query()
            ->where('workspace_id', ActingMember::of($actor)->workspace_id)
            ->where('domain', $payload->normalised())
            ->firstOrFail();

        $domain->delete();

        return $domain;
    }
}
