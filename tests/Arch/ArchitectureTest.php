<?php

declare(strict_types=1);

/*
 * The structure RFC 0013 sets, kept by tests rather than by memory
 * (ADR 0064, ADR 0065). As each context is built, it gains its own rule
 * that it uses the others only through their Features, Queries and Events.
 */

arch('the domain does not depend on the application')
    ->expect('Longhand')
    ->not->toUse('App');

arch('the domain knows nothing of transports')
    ->expect('Longhand')
    ->not->toUse([
        'Illuminate\Http',
        'Illuminate\Routing',
        'Illuminate\Support\Facades',
        'Inertia',
        'Laravel\Mcp',
    ]);

arch('domain code is strict')
    ->expect('Longhand')
    ->toUseStrictTypes();

arch('the shared kernel is final')
    ->expect('Longhand\Shared')
    ->classes()
    ->toBeFinal();

arch('no debugging calls are left behind')
    ->expect(['dd', 'dump', 'ray', 'var_dump'])
    ->not->toBeUsed();
