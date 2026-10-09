<?php

declare(strict_types=1);

namespace Longhand\Shared\Actions;

use Closure;
use Illuminate\Contracts\Container\Container;
use LogicException;
use Longhand\Shared\Actors\Actor;
use Longhand\Shared\Actors\AuthorisedActor;
use Longhand\Shared\Events\RecordedEvents;
use ReflectionClass;

/**
 * The one way an Action is run, on every surface (ADR 0065).
 *
 * In one transaction it checks the actor, takes the draft path when an
 * approval rule applies, runs the Action, and writes the audit entry and
 * the outbox. A refusal rolls everything back and is then logged on its
 * own, so what was refused is recorded without anything it attempted.
 */
final readonly class ActionRunner
{
    public function __construct(
        private Container $container,
        private Transactions $transactions,
        private Authoriser $authoriser,
        private ActionLog $log,
        private RecordedEvents $events,
    ) {}

    /**
     * @param  class-string  $actionClass
     */
    public function run(Actor $actor, string $actionClass, object $payload): mixed
    {
        $definition = $this->definitionOf($actionClass);

        try {
            return $this->transactions->run(function () use ($actor, $actionClass, $definition, $payload): mixed {
                $authorisation = $this->authoriser->authorise($actor, $definition, $payload);

                if (! $authorisation->allowed) {
                    throw new ActionRefused($definition, $authorisation->reason ?? 'This action is not allowed.');
                }

                $action = $this->container->make($actionClass);
                $authorised = $this->authorise($actor, $definition);

                $result = match (true) {
                    ! $authorisation->needsApproval => $action->handle($authorised, $payload),
                    $definition->approvable && $action instanceof Approvable => $action->draft($authorised, $payload),
                    default => throw new ActionRefused($definition, "{$definition->name} needs approval and has no draft path."),
                };

                $this->log->allowed($actor, $definition, $payload, $this->events->release());

                return $result;
            });
        } catch (ActionRefused $refusal) {
            $this->events->release();
            $this->log->refused($actor, $definition, $payload, $refusal->getMessage());

            throw $refusal;
        } finally {
            $this->events->release();
        }
    }

    /**
     * @param  class-string  $actionClass
     */
    private function definitionOf(string $actionClass): Action
    {
        $attributes = (new ReflectionClass($actionClass))->getAttributes(Action::class);

        if ($attributes === []) {
            throw new LogicException("{$actionClass} has no #[Action] attribute.");
        }

        return $attributes[0]->newInstance();
    }

    /**
     * AuthorisedActor's constructor is private; only the runner builds one.
     */
    private function authorise(Actor $actor, Action $definition): AuthorisedActor
    {
        $build = Closure::bind(
            static fn (): AuthorisedActor => new AuthorisedActor($actor, $definition),
            null,
            AuthorisedActor::class,
        );

        return $build();
    }
}
