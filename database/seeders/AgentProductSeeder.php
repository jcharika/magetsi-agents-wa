<?php

namespace Database\Seeders;

use App\Models\Agent;
use Illuminate\Database\Seeder;

class AgentProductSeeder extends Seeder
{
    public function run(): void
    {
        // Create a demo agent
        $agent = Agent::firstOrCreate(
            ['phone' => '263771234567'],
            [
                'name' => 'Tinashe M.',
                'wa_id' => '263771234567',
                'ecocash_number' => '0771234567',
            ]
        );

        // Seed ZESA product with default quick amounts
        $agent->products()->updateOrCreate(
            ['product_id' => 'zesa'],
            [
                'label' => 'ZESA Tokens',
                'icon' => '⚡',
                'currency' => 'ZWG',
                'min_amount' => 100,
                'quick_amounts' => [100, 200, 300, 500],
            ]
        );
    }
}
