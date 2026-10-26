<?php

namespace App\Actions;

use App\Models\TournamentGroup;
use Illuminate\Support\Collection;

class RecalculateGroupStandingsAction
{
    /**
     * Recalculate group table statistics and sort players by darts tie-breakers.
     *
     * @param TournamentGroup $group
     * @return Collection<int, array>
     */
    public function execute(TournamentGroup $group): Collection
    {
        // 1. Do finální tabulky se započítávají výhradně zápasy ve stavu 'completed'
        $completedMatches = $group->matches()
            ->where('status', 'completed')
            ->get();

        $players = $group->players()->get();

        // 2. Propočet statistik pro každého hráče
        $playerStats = [];

        foreach ($players as $player) {
            $played = 0;
            $won = 0;
            $drawn = 0;
            $lost = 0;
            $legsWon = 0;
            $legsLost = 0;
            $points = 0;

            foreach ($completedMatches as $m) {
                if ($m->player1_id === $player->id) {
                    $played++;
                    $p1Legs = (int) $m->player1_legs;
                    $p2Legs = (int) $m->player2_legs;
                    $legsWon += $p1Legs;
                    $legsLost += $p2Legs;

                    if ($p1Legs > $p2Legs) {
                        $won++;
                        $points += 2; // Výhra = 2 body
                    } elseif ($p1Legs === $p2Legs) {
                        $drawn++;
                        $points += 1; // Remíza = 1 bod
                    } else {
                        $lost++;
                    }
                } elseif ($m->player2_id === $player->id) {
                    $played++;
                    $p1Legs = (int) $m->player1_legs;
                    $p2Legs = (int) $m->player2_legs;
                    $legsWon += $p2Legs;
                    $legsLost += $p1Legs;

                    if ($p2Legs > $p1Legs) {
                        $won++;
                        $points += 2; // Výhra = 2 body
                    } elseif ($p2Legs === $p1Legs) {
                        $drawn++;
                        $points += 1; // Remíza = 1 bod
                    } else {
                        $lost++;
                    }
                }
            }

            $legDiff = $legsWon - $legsLost;

            // Uložíme hodnoty do pivotní tabulky
            $group->players()->updateExistingPivot($player->id, [
                'matches_played' => $played,
                'matches_won' => $won,
                'matches_drawn' => $drawn,
                'matches_lost' => $lost,
                'legs_won' => $legsWon,
                'legs_lost' => $legsLost,
                'points' => $points,
            ]);

            $playerStats[$player->id] = [
                'player_id' => $player->id,
                'name' => $player->name,
                'gender' => $player->gender,
                'gender_label' => $player->gender_label,
                'matches_played' => $played,
                'matches_won' => $won,
                'matches_drawn' => $drawn,
                'matches_lost' => $lost,
                'legs_won' => $legsWon,
                'legs_lost' => $legsLost,
                'legs_diff' => $legDiff,
                'points' => $points,
            ];
        }

        // 3. Řazení tabulky podle šipkových pravidel (Tie-breakers):
        //    1. Celkový počet bodů (Body)
        //    2. Rozdíl vyhraných a prohraných legů (+/-)
        //    3. Vyšší počet vyhraných legů
        //    4. Vzájemný zápas (Head-to-head)
        //    5. Abecední pořadí / los
        $sortedStats = collect(array_values($playerStats))->sort(function ($a, $b) use ($completedMatches) {
            // 1. Celkový počet bodů
            if ($a['points'] !== $b['points']) {
                return $b['points'] <=> $a['points'];
            }

            // 2. Rozdíl vyhraných a prohraných legů
            if ($a['legs_diff'] !== $b['legs_diff']) {
                return $b['legs_diff'] <=> $a['legs_diff'];
            }

            // 3. Vyšší počet vyhraných legů
            if ($a['legs_won'] !== $b['legs_won']) {
                return $b['legs_won'] <=> $a['legs_won'];
            }

            // 4. Vzájemný zápas (Head-to-Head) mezi těmito dvěma hráči
            $h2hMatch = $completedMatches->first(function ($m) use ($a, $b) {
                return ($m->player1_id === $a['player_id'] && $m->player2_id === $b['player_id'])
                    || ($m->player1_id === $b['player_id'] && $m->player2_id === $a['player_id']);
            });

            if ($h2hMatch) {
                $aLegs = $h2hMatch->player1_id === $a['player_id'] ? $h2hMatch->player1_legs : $h2hMatch->player2_legs;
                $bLegs = $h2hMatch->player1_id === $b['player_id'] ? $h2hMatch->player1_legs : $h2hMatch->player2_legs;

                if ($aLegs !== $bLegs) {
                    return $bLegs <=> $aLegs; // Vítěz vzájemného zápasu je výše
                }
            }

            // 5. Abecední pořadí podle jména
            return strcasecmp($a['name'], $b['name']);
        })->values();

        // 4. Přiřazení pozice (1..N) a uložení pořadí do seedu v pivotu
        $rankedStandings = $sortedStats->map(function ($item, $index) use ($group) {
            $rank = $index + 1;
            $item['rank'] = $rank;

            $group->players()->updateExistingPivot($item['player_id'], [
                'seed' => $rank,
            ]);

            return $item;
        });

        return $rankedStandings;
    }
}
