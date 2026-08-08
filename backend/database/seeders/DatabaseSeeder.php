<?php

namespace Database\Seeders;

use App\Models\Gym;
use App\Models\Member;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed a default gym (id=1) and 20 sample members for local development.
     * This makes the Members module immediately usable without an auth flow.
     */
    public function run(): void
    {
        $gym = Gym::firstOrCreate(
            ['id' => 1],
            [
                'name'              => 'IronDesk Demo Gym',
                'status'            => 'active',
                'subscription_tier' => 'standard',
            ]
        );

        // Create 20 active + 3 inactive sample members
        Member::factory()->count(20)->forGym($gym->id)->create();
        Member::factory()->count(3)->forGym($gym->id)->inactive()->create();
    }
}
