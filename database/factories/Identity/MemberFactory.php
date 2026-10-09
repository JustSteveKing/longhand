<?php

namespace Database\Factories\Identity;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Longhand\Identity\Enums\MemberKind;
use Longhand\Identity\Enums\MemberStatus;
use Longhand\Identity\Enums\Role;
use Longhand\Identity\Models\Member;
use Longhand\Identity\Models\Workspace;

/**
 * @extends Factory<Member>
 */
class MemberFactory extends Factory
{
    protected $model = Member::class;

    public function definition(): array
    {
        return [
            'workspace_id' => Workspace::factory(),
            'account_id' => User::factory(),
            'kind' => MemberKind::Human,
            'display_name' => fake()->name(),
            'handle' => fake()->unique()->regexify('[a-z]{8}'),
            'role' => Role::Member,
            'status' => MemberStatus::Active,
            'timezone' => 'Europe/London',
        ];
    }

    public function owner(): static
    {
        return $this->state(['role' => Role::Owner]);
    }

    public function admin(): static
    {
        return $this->state(['role' => Role::Admin]);
    }

    public function guest(): static
    {
        return $this->state(['role' => Role::Guest]);
    }
}
