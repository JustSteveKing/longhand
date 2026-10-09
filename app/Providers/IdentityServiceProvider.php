<?php

declare(strict_types=1);

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Longhand\Identity\Audit\AuditActionLog;
use Longhand\Identity\Authorisation\IdentityAuthoriser;
use Longhand\Shared\Actions\ActionLog;
use Longhand\Shared\Actions\Authoriser;
use Longhand\Shared\Actions\CompositeActionLog;

/**
 * Identity answers the runner's two questions: may this actor take this
 * action, and where is it recorded. Integration's outbox joins the log
 * when it exists.
 */
final class IdentityServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(Authoriser::class, IdentityAuthoriser::class);
        $this->app->bind(ActionLog::class, fn ($app): ActionLog => new CompositeActionLog([
            $app->make(AuditActionLog::class),
        ]));
    }
}
