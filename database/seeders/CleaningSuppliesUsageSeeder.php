<?php

namespace Database\Seeders;

use App\Models\ConsumableIssuance;
use App\Models\ConsumableItem;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class CleaningSuppliesUsageSeeder extends Seeder
{
    public function run(): void
    {
        $user = User::first();

        // Realistic usage history strings used across all cleaning supplies
        $usageDescriptions = [
            'Daily mopping of all corridors and common areas',
            'Weekly deep cleaning of all dormitory rooms',
            'Bathroom and toilet cleaning – all floors',
            'Room turnover cleaning after checkout',
            'Emergency spill cleanup – Ground floor lobby',
            'Weekly laundry of bed linens and pillowcases',
            'Monthly stripping and re-waxing of hallway floors',
            'Disinfection of common touch points (door handles, railings)',
            'Garbage collection and bin sanitation – all floors',
            'Kitchen and pantry area cleaning',
            'Glass and window cleaning – exterior facade',
            'Air freshener refill and dispenser maintenance',
            'Floor polishing – all floors (monthly)',
            'Deep scrubbing of shower stalls and bathroom tiles',
            'Laundry of bath towels and face towels',
            'Spot cleaning of carpet areas and rugs',
            'Restocking of tissue rolls and hand soap dispensers',
            'Pest control preparation – general surface cleaning',
            'Post-event cleanup – function hall and lobby',
            'Staircase and elevator area mopping',
        ];

        $items = ConsumableItem::inRandomOrder()->limit(20)->get();

        if ($items->isEmpty()) {
            $this->command->warn('No consumable items found. Run ConsumableSeeder first.');
            return;
        }

        $issuances = [];
        $baseDate  = Carbon::now()->subDays(90);

        foreach ($items as $index => $item) {
            // Seed 3–5 past issuances per item, each with a different usage description
            $count = rand(3, 5);
            for ($i = 0; $i < $count; $i++) {
                $issuedDate = (clone $baseDate)->addDays(rand(0, 85));
                $issuances[] = [
                    'consumable_item_id' => $item->id,
                    'quantity'           => rand(1, 10),
                    'issued_date'        => $issuedDate->toDateString(),
                    'usage_history'      => $usageDescriptions[($index + $i) % count($usageDescriptions)],
                    'issued_by'          => $user?->id,
                    'created_at'         => $issuedDate,
                    'updated_at'         => $issuedDate,
                ];
            }
        }

        foreach ($issuances as $data) {
            ConsumableIssuance::create($data);
        }

        $this->command->info('CleaningSuppliesUsageSeeder: seeded ' . count($issuances) . ' issuances with usage history.');
    }
}
