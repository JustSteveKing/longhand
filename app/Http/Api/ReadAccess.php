<?php

declare(strict_types=1);

namespace App\Http\Api;

use Longhand\Shared\Actions\Action;
use Longhand\Shared\Actions\ActionRefused;
use Longhand\Shared\Actions\Authoriser;
use Longhand\Shared\Actions\NoInput;
use Longhand\Shared\Actors\Actor;

/**
 * Reads are not actions and are not audited, but they pass the same
 * checks: an active member, the scope on the token and in the role.
 */
final readonly class ReadAccess
{
    public function __construct(private Authoriser $authoriser) {}

    public function require(Actor $actor, string $name, ?string $scope): void
    {
        $action = new Action($name, scope: $scope, approvable: false);
        $authorisation = $this->authoriser->authorise($actor, $action, new NoInput);

        if (! $authorisation->allowed) {
            throw new ActionRefused($action, $authorisation->reason ?? 'Not allowed.', $authorisation->code ?? 'insufficient-scope');
        }
    }
}
