<?php

declare(strict_types=1);

namespace Longhand\Shared\Actions;

use Closure;
use Illuminate\Database\ConnectionInterface;

final readonly class DatabaseTransactions implements Transactions
{
    public function __construct(private ConnectionInterface $connection) {}

    public function run(Closure $work): mixed
    {
        return $this->connection->transaction($work);
    }
}
