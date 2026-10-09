<?php

declare(strict_types=1);

namespace Longhand\Shared\Events;

/**
 * Something that happened in a context.
 *
 * Domain events are internal. Integration maps them to CloudEvents for
 * anyone outside (ADR 0050, ADR 0051).
 */
interface DomainEvent {}
