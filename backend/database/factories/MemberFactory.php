<?php

namespace Database\Factories;

use App\Models\Gym;
use App\Models\Member;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Member>
 */
class MemberFactory extends Factory
{
    protected $model = Member::class;

    public function definition(): array
    {
        return [
            'ulid'                    => (string) Str::ulid(),
            'gym_id'                  => Gym::factory(),
            'full_name'               => $this->faker->name(),
            'date_of_birth'           => $this->faker->dateTimeBetween('-60 years', '-16 years')->format('Y-m-d'),
            'gender'                  => $this->faker->randomElement(['male', 'female', 'other']),
            'phone'                   => $this->faker->numerify('##########'),
            'emergency_contact_name'  => $this->faker->name(),
            'emergency_contact_phone' => $this->faker->numerify('##########'),
            'photo_url'               => null,
            'registration_date'       => now()->toDateString(),
            'status'                  => 'active',
        ];
    }

    public function inactive(): static
    {
        return $this->state(['status' => 'inactive']);
    }

    public function forGym(int $gymId): static
    {
        return $this->state(['gym_id' => $gymId]);
    }
}
