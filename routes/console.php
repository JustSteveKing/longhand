<?php

use Illuminate\Support\Facades\Schedule;
use Longhand\Identity\Features\ExpireInvitations\ExpireInvitations;
use Longhand\Shared\Actions\ActionRunner;
use Longhand\Shared\Actions\NoInput;
use Longhand\Shared\Actors\Actor;

// Scheduled work runs as the system, through the same runner and audit
// log as everything else (ADR 0065).
Schedule::call(fn () => app(ActionRunner::class)->run(Actor::system(), ExpireInvitations::class, new NoInput))
    ->name('invitation.expire')
    ->hourly()
    ->withoutOverlapping();
