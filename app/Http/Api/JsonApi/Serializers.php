<?php

declare(strict_types=1);

namespace App\Http\Api\JsonApi;

use Illuminate\Contracts\Container\Container;
use Illuminate\Database\Eloquent\Model;
use LogicException;

/**
 * Which serializer handles which model, so included resources of any
 * type can be rendered.
 */
final readonly class Serializers
{
    /**
     * @param  array<class-string<Model>, class-string<Serializer>>  $map
     */
    public function __construct(
        private Container $container,
        private array $map,
    ) {}

    public function for(Model $model): Serializer
    {
        $class = $this->map[$model::class] ?? throw new LogicException('No serializer for '.$model::class.'.');

        return $this->container->make($class);
    }
}
