<?php

namespace Database\Seeders;

use App\Models\StepUpChallenge;
use Illuminate\Database\Seeder;

/**
 * Placeholder symbols so the challenge is functional out of the box. A Super
 * Admin replaces these with the business's own set — the security comes from
 * the answers being secret, so seeded ones must be changed before going live.
 */
class StepUpChallengeSeeder extends Seeder
{
    public function run(): void
    {
        if (StepUpChallenge::query()->exists()) {
            return;
        }

        foreach ([['Ankh', '☥', 'change-me-one'], ['Eye of Horus', '𓂀', 'change-me-two'], ['Scarab', '𓆣', 'change-me-three']] as [$label, $symbol, $answer]) {
            $challenge = new StepUpChallenge(['label' => $label, 'symbol_text' => $symbol, 'is_active' => true]);
            $challenge->setAnswer($answer);
            $challenge->save();
        }
    }
}
