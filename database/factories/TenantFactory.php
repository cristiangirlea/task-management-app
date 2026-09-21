<?php

namespace Database\Factories;

use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Tenant>
 */
class TenantFactory extends Factory
{
    protected $model = Tenant::class;

    public function definition(): array
    {
        $name = $this->faker->unique()->company();

        return [
            'name' => $name,
            'slug' => Str::slug($name), // Generate slug from name
            'domain' => $this->faker->unique()->domainName(),
            'settings' => [
                'theme' => $this->faker->randomElement(['light', 'dark']),
                'notifications' => $this->faker->boolean(),
            ], // Generate random JSON settings
        ];
    }
}
