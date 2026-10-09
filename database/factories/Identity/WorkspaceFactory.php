<?php

namespace Database\Factories\Identity;

use Illuminate\Database\Eloquent\Factories\Factory;
use Longhand\Identity\Models\Workspace;

/**
 * @extends Factory<Workspace>
 */
class WorkspaceFactory extends Factory
{
    protected $model = Workspace::class;

    public function definition(): array
    {
        return [
            'name' => fake()->company(),
            'handle' => fake()->unique()->regexify('[a-z]{6}-[a-z0-9]{4}'),
            'default_timezone' => 'Europe/London',
        ];
    }
}
