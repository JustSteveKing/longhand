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

/*
 * Octane keeps the application in memory between requests (ADR 0071), so
 * anything held in a static property leaks from one request into the
 * next. State belongs in scoped container bindings, which Octane resets
 * on every request. Statics inherited from the framework, such as
 * Eloquent's, are the framework's to manage.
 */
it('keeps no state in static properties in the domain', function (): void {
    $offenders = [];

    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator(dirname(__DIR__, 2).'/src')) as $file) {
        if ($file->getExtension() !== 'php') {
            continue;
        }

        $class = 'Longhand\\'.str_replace(['/', '.php'], ['\\', ''], substr($file->getPathname(), strlen(dirname(__DIR__, 2).'/src/')));

        if (! class_exists($class) && ! trait_exists($class)) {
            continue;
        }

        foreach ((new ReflectionClass($class))->getProperties(ReflectionProperty::IS_STATIC) as $property) {
            // Only what our own code declares; the framework's base classes
            // manage their own statics for Octane.
            if (str_starts_with($property->getDeclaringClass()->getName(), 'Longhand\\')) {
                $offenders[] = "{$class}::\${$property->getName()}";
            }
        }
    }

    expect($offenders)->toBe([]);
});

arch('Identity depends on no other context')
    ->expect('Longhand\Identity')
    ->not->toUse([
        'Longhand\Conversations',
        'Longhand\Commitments',
        'Longhand\Attention',
        'Longhand\Briefs',
        'Longhand\CheckIns',
        'Longhand\Search',
        'Longhand\Integration',
    ]);
