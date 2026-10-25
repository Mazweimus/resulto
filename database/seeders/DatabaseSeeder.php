<?php

namespace Database\Seeders;

use App\Models\GameMatch;
use App\Models\Player;
use App\Models\Tournament;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // 1. Admin User
        User::firstOrCreate(
            ['email' => 'admin@darts.cz'],
            [
                'name' => 'David Grey',
                'password' => Hash::make('password'),
            ]
        );

        // 2. Šipkaři (jméno a pohlaví)
        $playersData = [
            ['name' => 'Luke Littler', 'gender' => 'male'],
            ['name' => 'Michael van Gerwen', 'gender' => 'male'],
            ['name' => 'Luke Humphries', 'gender' => 'male'],
            ['name' => 'Gerwyn Price', 'gender' => 'male'],
            ['name' => 'Michael Smith', 'gender' => 'male'],
            ['name' => 'Rob Cross', 'gender' => 'male'],
            ['name' => 'Nathan Aspinall', 'gender' => 'male'],
            ['name' => 'Peter Wright', 'gender' => 'male'],
        ];

        $players = [];
        foreach ($playersData as $data) {
            $players[] = Player::firstOrCreate(
                ['name' => $data['name']],
                ['gender' => $data['gender']]
            );
        }

        // Doplnění šipkařek do databáze hráčů
        Player::firstOrCreate(['name' => 'Fallon Sherrock'], ['gender' => 'female']);
        Player::firstOrCreate(['name' => 'Beau Greaves'], ['gender' => 'female']);

        $playerIds = array_map(fn($p) => $p->id, $players);

        // 3. Ukončený turnaj s pódiem: Prague Darts Open 2026
        $finished = Tournament::where('name', 'Prague Darts Open 2026')->first();
        if (!$finished) {
            $finished = Tournament::create([
                'name' => 'Prague Darts Open 2026',
                'date' => now()->subDays(10)->toDateString(),
                'status' => 'finished',
                'game_mode' => '501',
                'checkout_mode' => 'double_out',
                'format' => 'double_elimination',
                'best_of_legs' => 5,
                'winner_id' => $players[0]->id,       // 1. místo: Luke Littler
                'second_place_id' => $players[1]->id, // 2. místo: Michael van Gerwen
                'third_place_id' => $players[2]->id,  // 3. místo: Luke Humphries
            ]);
            $finished->players()->attach($playerIds);

            // Vygenerování zápasů a simulace výsledků pro finished turnaj
            $finished->generateDoubleEliminationMatches($playerIds);

            // Helper pro odehrání zápasu a posun
            $playMatch = function (GameMatch $match, int $p1Legs, int $p2Legs) {
                $winnerId = ($p1Legs > $p2Legs) ? $match->player1_id : $match->player2_id;
                $loserId = ($p1Legs > $p2Legs) ? $match->player2_id : $match->player1_id;
                $match->update([
                    'player1_legs' => $p1Legs,
                    'player2_legs' => $p2Legs,
                    'winner_id' => $winnerId,
                    'loser_id' => $loserId,
                    'status' => 'completed',
                ]);
                $match->advanceParticipants();
            };

            // Odehrání celého pavouka pro finished turnaj
            $w1_1 = $finished->matches()->where('bracket_type', 'winners')->where('round', 1)->where('match_number', 1)->first();
            $w1_2 = $finished->matches()->where('bracket_type', 'winners')->where('round', 1)->where('match_number', 2)->first();
            $w1_3 = $finished->matches()->where('bracket_type', 'winners')->where('round', 1)->where('match_number', 3)->first();
            $w1_4 = $finished->matches()->where('bracket_type', 'winners')->where('round', 1)->where('match_number', 4)->first();
            if ($w1_1) $playMatch($w1_1, 3, 1);
            if ($w1_2) $playMatch($w1_2, 3, 0);
            if ($w1_3) $playMatch($w1_3, 3, 1);
            if ($w1_4) $playMatch($w1_4, 3, 2);

            $w2_1 = $finished->matches()->where('bracket_type', 'winners')->where('round', 2)->where('match_number', 1)->first();
            $w2_2 = $finished->matches()->where('bracket_type', 'winners')->where('round', 2)->where('match_number', 2)->first();
            if ($w2_1) $playMatch($w2_1, 3, 2);
            if ($w2_2) $playMatch($w2_2, 3, 1);

            $w3_1 = $finished->matches()->where('bracket_type', 'winners')->where('round', 3)->where('match_number', 1)->first();
            if ($w3_1) $playMatch($w3_1, 3, 2);

            $l1_1 = $finished->matches()->where('bracket_type', 'losers')->where('round', 1)->where('match_number', 1)->first();
            $l1_2 = $finished->matches()->where('bracket_type', 'losers')->where('round', 1)->where('match_number', 2)->first();
            if ($l1_1) $playMatch($l1_1, 3, 1);
            if ($l1_2) $playMatch($l1_2, 3, 2);

            $l2_1 = $finished->matches()->where('bracket_type', 'losers')->where('round', 2)->where('match_number', 1)->first();
            $l2_2 = $finished->matches()->where('bracket_type', 'losers')->where('round', 2)->where('match_number', 2)->first();
            if ($l2_1) $playMatch($l2_1, 3, 0);
            if ($l2_2) $playMatch($l2_2, 3, 1);

            $l3_1 = $finished->matches()->where('bracket_type', 'losers')->where('round', 3)->where('match_number', 1)->first();
            if ($l3_1) $playMatch($l3_1, 3, 2);

            $l4_1 = $finished->matches()->where('bracket_type', 'losers')->where('round', 4)->where('match_number', 1)->first();
            if ($l4_1) $playMatch($l4_1, 3, 2);

            $gf1 = $finished->matches()->where('bracket_type', 'grand_final')->where('round', 1)->first();
            if ($gf1) $playMatch($gf1, 3, 1);

            $finished->recalculateStatus();
        }

        // 4. Běžící turnaj: Czech Darts Masters 2026 (Double Elimination, 8 hráčů)
        $ongoing = Tournament::where('name', 'Czech Darts Masters 2026')->first();
        if (!$ongoing) {
            $ongoing = Tournament::create([
                'name' => 'Czech Darts Masters 2026',
                'date' => now()->toDateString(),
                'status' => 'ongoing',
                'game_mode' => '501',
                'checkout_mode' => 'double_out',
                'format' => 'double_elimination',
                'best_of_legs' => 7,
            ]);
            $ongoing->players()->attach($playerIds);

            // Vygenerování kompletního Double Elimination pavouka (Větev A, Větev B, Grand Finále)
            $ongoing->generateDoubleEliminationMatches($playerIds);

            // Simulujeme odehrání dvou zápasů v 1. kole Větve A (Čtvrtfinále A)
            $m1 = $ongoing->matches()->where('bracket_type', 'winners')->where('round', 1)->where('match_number', 1)->first();
            if ($m1) {
                $m1->update([
                    'player1_legs' => 4,
                    'player2_legs' => 1,
                    'winner_id' => $m1->player1_id,
                    'loser_id' => $m1->player2_id,
                    'status' => 'completed',
                ]);
                $m1->advanceParticipants();
            }

            $m2 = $ongoing->matches()->where('bracket_type', 'winners')->where('round', 1)->where('match_number', 2)->first();
            if ($m2) {
                $m2->update([
                    'player1_legs' => 4,
                    'player2_legs' => 2,
                    'winner_id' => $m2->player1_id,
                    'loser_id' => $m2->player2_id,
                    'status' => 'completed',
                ]);
                $m2->advanceParticipants();
            }
        }
    }
}
