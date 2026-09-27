<?php

namespace Database\Factories;

use App\Models\Invitation;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Invitation>
 */
class InvitationFactory extends Factory
{
    protected $model = Invitation::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'email' => $this->faker->unique()->safeEmail(),
            'invited_by' => fn (array $attributes) => User::factory()->create(['tenant_id' => $attributes['tenant_id']])->id,
            'expires_at' => now()->addDays(Invitation::LIFETIME_DAYS),
        ];
    }

    public function expired(): static
    {
        return $this->state(fn () => ['expires_at' => now()->subDay()]);
    }

    public function accepted(): static
    {
        return $this->state(fn () => ['accepted_at' => now()]);
    }
}
