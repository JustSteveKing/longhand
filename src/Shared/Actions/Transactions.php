<?php

declare(strict_types=1);

namespace Longhand\Shared\Actions;

use Closure;

/**
 * Runs work in one database transaction.
 *
 * The runner depends on this rather than on a database connection, so
 * it can be tested without one.
 */
interface Transactions
{
    /**
     * @template TResult
     *
     * @param  Closure(): TResult  $work
     * @return TResult
     */
    public function run(Closure $work): mixed;
}
