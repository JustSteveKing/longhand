<?php

declare(strict_types=1);

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Longhand\Shared\Actions\ActionLog;
use Longhand\Shared\Actions\ActionRunner;
use Longhand\Shared\Actions\Authoriser;
use Longhand\Shared\Actions\DatabaseTransactions;
use Longhand\Shared\Actions\NullActionLog;
use Longhand\Shared\Actions\ScopeAuthoriser;
use Longhand\Shared\Actions\Transactions;
use Longhand\Shared\Events\RecordedEvents;

/**
 * Wires the shared kernel: the Action runner and what it depends on.
 *
 * The scope-only authoriser and the null log are fallbacks; Identity's
 * provider, registered after this one, binds the real ones.
 */
final class SharedServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(Authoriser::class, ScopeAuthoriser::class);
        $this->app->bind(ActionLog::class, NullActionLog::class);
        $this->app->bind(Transactions::class, fn ($app): Transactions => new DatabaseTransactions($app->make('db')->connection()));
        $this->app->scoped(RecordedEvents::class);

        $this->app->scoped(ActionRunner::class, fn ($app): ActionRunner => new ActionRunner(
            container: $app,
            transactions: $app->make(Transactions::class),
            authoriser: $app->make(Authoriser::class),
            log: $app->make(ActionLog::class),
            events: $app->make(RecordedEvents::class),
        ));
    }
}
