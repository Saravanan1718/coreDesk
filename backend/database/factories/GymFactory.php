<?php

namespace Database\Factories;

use App\Models\Gym;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Gym>
 */
class GymFactory extends Factory
{
    protected $model = Gym::class;

    public function definition(): array
    {
        return [
            'name'              => $this->faker->company() . ' Gym',
            'status'            => 'active',
            'subscription_tier' => 'standard',
            'suspended_at'      => null,
        ];
    }

    public function suspended(): static
    {
        return $this->state([
            'status'       => 'suspended',
            'suspended_at' => now(),
        ]);
    }
}
