<?php

declare(strict_types=1);

use Illuminate\Container\Container;
use Longhand\Shared\Actions\Action;
use Longhand\Shared\Actions\ActionLog;
use Longhand\Shared\Actions\ActionRefused;
use Longhand\Shared\Actions\ActionRunner;
use Longhand\Shared\Actions\Approvable;
use Longhand\Shared\Actions\Authorisation;
use Longhand\Shared\Actions\Authoriser;
use Longhand\Shared\Actions\ScopeAuthoriser;
use Longhand\Shared\Actions\Transactions;
use Longhand\Shared\Actors\Actor;
use Longhand\Shared\Actors\AuthorisedActor;
use Longhand\Shared\Actors\Surface;
use Longhand\Shared\Events\DomainEvent;
use Longhand\Shared\Events\RecordedEvents;

final class FakeTransactions implements Transactions
{
    public int $committed = 0;

    public int $rolledBack = 0;

    public function run(Closure $work): mixed
    {
        try {
            $result = $work();
            $this->committed++;

            return $result;
        } catch (Throwable $exception) {
            $this->rolledBack++;

            throw $exception;
        }
    }
}

final class FakeActionLog implements ActionLog
{
    /** @var list<array{action: string, events: int}> */
    public array $allowed = [];

    /** @var list<array{action: string, reason: string}> */
    public array $refused = [];

    public function allowed(Actor $actor, Action $action, object $payload, mixed $result, array $events): void
    {
        $this->allowed[] = ['action' => $action->name, 'events' => count($events)];
    }

    public function refused(Actor $actor, Action $action, object $payload, string $reason): void
    {
        $this->refused[] = ['action' => $action->name, 'reason' => $reason];
    }
}

final class FixedAuthoriser implements Authoriser
{
    public function __construct(private Authorisation $authorisation) {}

    public function authorise(Actor $actor, Action $action, object $payload): Authorisation
    {
        return $this->authorisation;
    }
}

final class SomethingHappened implements DomainEvent {}

final readonly class GreetPayload
{
    public function __construct(public string $name) {}
}

#[Action('greeting.send', scope: 'threads:write')]
final readonly class SendGreeting implements Approvable
{
    public function __construct(private RecordedEvents $events) {}

    public function handle(AuthorisedActor $actor, GreetPayload $payload): string
    {
        $this->events->record(new SomethingHappened);

        return "Hello, {$payload->name}";
    }

    public function draft(AuthorisedActor $actor, object $payload): string
    {
        return 'draft';
    }
}

#[Action('greeting.fail', scope: 'threads:write')]
final readonly class FailingGreeting
{
    public function __construct(private RecordedEvents $events) {}

    public function handle(AuthorisedActor $actor, GreetPayload $payload): never
    {
        $this->events->record(new SomethingHappened);

        throw new RuntimeException('Something broke.');
    }
}

final readonly class UndeclaredAction
{
    public function handle(AuthorisedActor $actor, GreetPayload $payload): void {}
}

function runner(?Authoriser $authoriser = null, ?FakeTransactions $transactions = null, ?FakeActionLog $log = null): ActionRunner
{
    $container = new Container;
    $events = new RecordedEvents;
    $container->instance(RecordedEvents::class, $events);

    return new ActionRunner(
        container: $container,
        transactions: $transactions ?? new FakeTransactions,
        authoriser: $authoriser ?? new ScopeAuthoriser,
        log: $log ?? new FakeActionLog,
        events: $events,
    );
}

function member(array $scopes = ['threads:write']): Actor
{
    return new Actor(memberId: 'mem_01JA7Q0000000000000000000A', surface: Surface::Rest, scopes: $scopes);
}

it('runs an allowed action in a transaction and logs it with its events', function (): void {
    $transactions = new FakeTransactions;
    $log = new FakeActionLog;

    $result = runner(transactions: $transactions, log: $log)->run(member(), SendGreeting::class, new GreetPayload('Priya'));

    expect($result)->toBe('Hello, Priya')
        ->and($transactions->committed)->toBe(1)
        ->and($log->allowed)->toBe([['action' => 'greeting.send', 'events' => 1]])
        ->and($log->refused)->toBe([]);
});

it('refuses a call without the scope, rolls back, and logs the refusal', function (): void {
    $transactions = new FakeTransactions;
    $log = new FakeActionLog;

    expect(fn () => runner(transactions: $transactions, log: $log)->run(member(scopes: []), SendGreeting::class, new GreetPayload('Priya')))
        ->toThrow(ActionRefused::class, 'threads:write');

    expect($transactions->rolledBack)->toBe(1)
        ->and($log->allowed)->toBe([])
        ->and($log->refused)->toHaveCount(1)
        ->and($log->refused[0]['action'])->toBe('greeting.send');
});

it('takes the draft path when approval is required', function (): void {
    $runner = runner(authoriser: new FixedAuthoriser(Authorisation::approvalRequired()));

    expect($runner->run(member(), SendGreeting::class, new GreetPayload('Priya')))->toBe('draft');
});

it('discards events recorded by an action that fails', function (): void {
    $log = new FakeActionLog;
    $runner = runner(log: $log);

    expect(fn () => $runner->run(member(), FailingGreeting::class, new GreetPayload('Priya')))
        ->toThrow(RuntimeException::class, 'Something broke.');

    $runner->run(member(), SendGreeting::class, new GreetPayload('Priya'));

    expect($log->allowed)->toBe([['action' => 'greeting.send', 'events' => 1]]);
});

it('lets scheduled work run as the system without scopes', function (): void {
    expect(runner()->run(Actor::system(), SendGreeting::class, new GreetPayload('team')))->toBe('Hello, team');
});

it('rejects a class with no #[Action] attribute', function (): void {
    expect(fn () => runner()->run(member(), UndeclaredAction::class, new GreetPayload('Priya')))
        ->toThrow(LogicException::class, '#[Action]');
});

it('cannot construct an AuthorisedActor outside the runner', function (): void {
    expect((new ReflectionClass(AuthorisedActor::class))->getConstructor()?->isPrivate())->toBeTrue();
});
