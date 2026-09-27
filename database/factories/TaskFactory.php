<?php

namespace Database\Factories;

use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Task>
 */
class TaskFactory extends Factory
{
    protected $model = Task::class;

    public function definition(): array
    {
        return [
            'project_id' => Project::factory(),
            // Tasks live in the tenant of their project; the assignee is a user of that tenant.
            'tenant_id' => fn (array $attributes) => Project::withoutGlobalScopes()
                ->whereKey($attributes['project_id'])
                ->value('tenant_id'),
            'user_id' => fn (array $attributes) => User::factory()->create(['tenant_id' => $attributes['tenant_id']])->id,
            'title' => $this->faker->sentence(3),
            'description' => $this->faker->paragraph(),
            'status' => $this->faker->randomElement(Task::STATUSES),
            'priority' => $this->faker->numberBetween(1, 5),
            'position' => 0,
            'due_date' => $this->faker->dateTimeBetween('now', '+1 year'),
        ];
    }

    public function pending(): static
    {
        return $this->state(fn () => ['status' => Task::STATUS_PENDING]);
    }

    public function inProgress(): static
    {
        return $this->state(fn () => ['status' => Task::STATUS_IN_PROGRESS]);
    }

    public function completed(): static
    {
        return $this->state(fn () => ['status' => Task::STATUS_COMPLETED]);
    }
}
