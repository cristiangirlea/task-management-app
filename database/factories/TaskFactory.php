<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use App\Models\User;
use App\Models\Project;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Task>
 */
class TaskFactory extends Factory
{
    protected $model = \App\Models\Task::class;

    public function definition(): array
    {
        return [
            'title' => $this->faker->sentence(3), // Example: "Complete the project"
            'description' => $this->faker->paragraph(), // Example: "This task involves..."
            'status' => $this->faker->randomElement(['pending', 'in_progress', 'completed']),
            'priority' => $this->faker->numberBetween(1, 5), // Priority between 1 (highest) and 5 (lowest)
            'due_date' => $this->faker->dateTimeBetween('now', '+1 year'), // Future date
            'user_id' => User::factory(), // Associate the task with a user
            'project_id' => Project::factory(), // Associate the task with a project
        ];
    }
}
