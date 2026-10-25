<?php

namespace App\Services;

use App\Models\GameMatch;
use App\Models\Player;
use App\Models\Tournament;
use App\Models\TournamentStanding;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class TournamentService
{
    /**
     * Calculate final positions (standings) of players after tournament is played.
     *
     * @param Tournament $tournament
     * @return array<int, array{rank: string, rank_num: int, player: Player, player_name: string}>
     */
    public function calculateStandings(Tournament $tournament): array
    {
        $tournament->loadMissing([
            'matches.player1',
            'matches.player2',
            'matches.winner',
            'matches.loser',
            'players',
        ]);

        if (in_array($tournament->format, ['double_elimination', 'free_double_elimination'])) {
            return $this->calculateDoubleEliminationStandings($tournament);
        }

        if ($tournament->format === 'free_oneway_elimination') {
            return $this->calculateFreeOnewayStandings($tournament);
        }

        return $this->calculateKnockoutStandings($tournament);
    }

    /**
     * Calculate standings for FREE Oneway Elimination (Single + mandatory 3rd/4th place match).
     * - 1. place: Winner of Final
     * - 2. place: Loser of Final
     * - 3. place: Winner of 3rd place match
     * - 4. place: Loser of 3rd place match
     * - 5.–8., 9.–16., etc.: Losers of prior elimination rounds (real players only)
     */
    protected function calculateFreeOnewayStandings(Tournament $tournament): array
    {
        $standings = [];
        $rankedPlayerIds = [];

        $rounds = $tournament->matches
            ->where('bracket_type', 'winners')
            ->groupBy('round')
            ->sortKeysDesc();

        $maxRound = $rounds->keys()->first();
        $finalMatch = $rounds->get($maxRound)?->first();
        $thirdPlaceMatch = $tournament->thirdPlaceMatch;

        // 1. and 2. place from Final
        if ($finalMatch && $finalMatch->status === 'completed' && $finalMatch->winner) {
            $standings[] = [
                'rank' => '1.',
                'rank_num' => 1,
                'rank_from' => 1,
                'rank_to' => 1,
                'points' => TournamentStanding::calculatePoints(1, 1),
                'player' => $finalMatch->winner,
                'player_name' => $finalMatch->winner->name,
            ];
            $rankedPlayerIds[] = $finalMatch->winner->id;

            if ($finalMatch->loser) {
                $standings[] = [
                    'rank' => '2.',
                    'rank_num' => 2,
                    'rank_from' => 2,
                    'rank_to' => 2,
                    'points' => TournamentStanding::calculatePoints(2, 2),
                    'player' => $finalMatch->loser,
                    'player_name' => $finalMatch->loser->name,
                ];
                $rankedPlayerIds[] = $finalMatch->loser->id;
            }
        }

        // 3. and 4. place from 3rd place match
        if ($thirdPlaceMatch && $thirdPlaceMatch->status === 'completed' && $thirdPlaceMatch->winner) {
            $standings[] = [
                'rank' => '3.',
                'rank_num' => 3,
                'rank_from' => 3,
                'rank_to' => 3,
                'points' => TournamentStanding::calculatePoints(3, 3),
                'player' => $thirdPlaceMatch->winner,
                'player_name' => $thirdPlaceMatch->winner->name,
            ];
            $rankedPlayerIds[] = $thirdPlaceMatch->winner->id;

            if ($thirdPlaceMatch->loser) {
                $standings[] = [
                    'rank' => '4.',
                    'rank_num' => 4,
                    'rank_from' => 4,
                    'rank_to' => 4,
                    'points' => TournamentStanding::calculatePoints(4, 4),
                    'player' => $thirdPlaceMatch->loser,
                    'player_name' => $thirdPlaceMatch->loser->name,
                ];
                $rankedPlayerIds[] = $thirdPlaceMatch->loser->id;
            }
        }

        $currentRank = count($standings) + 1;

        // Elimination rounds prior to semifinals (semifinal losers already ranked 3rd & 4th)
        $semiRound = $maxRound ? ($maxRound - 1) : 0;

        foreach ($rounds as $roundNum => $matchesInRound) {
            if ($roundNum >= $semiRound) {
                continue;
            }

            $eliminated = [];
            foreach ($matchesInRound as $m) {
                if ($m->status === 'completed' && $m->loser && !$m->is_bye) {
                    if (!in_array($m->loser->id, $rankedPlayerIds)) {
                        $eliminated[] = $m->loser;
                        $rankedPlayerIds[] = $m->loser->id;
                    }
                }
            }

            $count = count($eliminated);
            if ($count === 0) {
                continue;
            }

            $rankFrom = $currentRank;
            $rankTo = $currentRank + $count - 1;
            $rankStr = ($count === 1)
                ? "{$rankFrom}."
                : "{$rankFrom}.–{$rankTo}.";
            $points = TournamentStanding::calculatePoints($rankFrom, $rankTo);

            foreach ($eliminated as $player) {
                $standings[] = [
                    'rank' => $rankStr,
                    'rank_num' => $rankFrom,
                    'rank_from' => $rankFrom,
                    'rank_to' => $rankTo,
                    'points' => $points,
                    'player' => $player,
                    'player_name' => $player->name,
                ];
            }

            $currentRank += $count;
        }

        // Fallback: unranked players
        $unrankedPlayers = $tournament->players->filter(function ($p) use ($rankedPlayerIds) {
            return !in_array($p->id, $rankedPlayerIds);
        });

        if ($unrankedPlayers->isNotEmpty()) {
            $countUnranked = $unrankedPlayers->count();
            $rankFrom = $currentRank;
            $rankTo = $currentRank + $countUnranked - 1;
            $rankStr = ($countUnranked === 1)
                ? "{$rankFrom}."
                : "{$rankFrom}.–{$rankTo}.";
            $points = TournamentStanding::calculatePoints($rankFrom, $rankTo);

            foreach ($unrankedPlayers as $player) {
                $standings[] = [
                    'rank' => $rankStr,
                    'rank_num' => $rankFrom,
                    'rank_from' => $rankFrom,
                    'rank_to' => $rankTo,
                    'points' => $points,
                    'player' => $player,
                    'player_name' => $player->name,
                ];
            }
        }

        return $standings;
    }

    /**
     * Calculate standings for Double Elimination format:
     * - 1. place: Winner of Grand Final
     * - 2. place: Loser of Grand Final
     * - 3. place: Loser of Losers Bracket Final
     * - 4. place: Loser of Losers Bracket Semifinal
     * - Tied ranks (e.g. 5.–6., 7.–8., 9.–12.): Players eliminated in the same round of Losers Bracket
     */
    protected function calculateDoubleEliminationStandings(Tournament $tournament): array
    {
        $standings = [];
        $rankedPlayerIds = [];

        // 1. Grand Final winner (1st) and loser (2nd)
        $gfMatches = $tournament->matches
            ->where('bracket_type', 'grand_final')
            ->sortBy('match_number');

        $gf1 = $gfMatches->where('is_reset_match', false)->first();
        $gf2 = $gfMatches->where('is_reset_match', true)->first();

        $firstPlace = null;
        $secondPlace = null;

        if ($gf2 && $gf2->status === 'completed' && $gf2->winner) {
            $firstPlace = $gf2->winner;
            $secondPlace = $gf2->loser;
        } elseif ($gf1 && $gf1->status === 'completed' && $gf1->winner) {
            $firstPlace = $gf1->winner;
            $secondPlace = $gf1->loser;
        }

        if ($firstPlace) {
            $standings[] = [
                'rank' => '1.',
                'rank_num' => 1,
                'rank_from' => 1,
                'rank_to' => 1,
                'points' => TournamentStanding::calculatePoints(1, 1),
                'player' => $firstPlace,
                'player_name' => $firstPlace->name,
            ];
            $rankedPlayerIds[] = $firstPlace->id;
        }

        if ($secondPlace && !in_array($secondPlace->id, $rankedPlayerIds)) {
            $standings[] = [
                'rank' => '2.',
                'rank_num' => 2,
                'rank_from' => 2,
                'rank_to' => 2,
                'points' => TournamentStanding::calculatePoints(2, 2),
                'player' => $secondPlace,
                'player_name' => $secondPlace->name,
            ];
            $rankedPlayerIds[] = $secondPlace->id;
        }

        // 2. Losers Bracket elimination rounds from latest round down to round 1
        $losersRounds = $tournament->matches
            ->where('bracket_type', 'losers')
            ->groupBy('round')
            ->sortKeysDesc();

        $currentRank = count($standings) + 1;

        foreach ($losersRounds as $roundNum => $matchesInRound) {
            $eliminatedInRound = [];
            foreach ($matchesInRound as $m) {
                if ($m->status === 'completed' && $m->loser && !$m->is_bye) {
                    if (!in_array($m->loser->id, $rankedPlayerIds)) {
                        $eliminatedInRound[] = $m->loser;
                        $rankedPlayerIds[] = $m->loser->id;
                    }
                }
            }

            $countInRound = count($eliminatedInRound);
            if ($countInRound === 0) {
                continue;
            }

            $rankFrom = $currentRank;
            $rankTo = $currentRank + $countInRound - 1;
            $rankStr = ($countInRound === 1)
                ? "{$rankFrom}."
                : "{$rankFrom}.–{$rankTo}.";
            $points = TournamentStanding::calculatePoints($rankFrom, $rankTo);

            foreach ($eliminatedInRound as $player) {
                $standings[] = [
                    'rank' => $rankStr,
                    'rank_num' => $rankFrom,
                    'rank_from' => $rankFrom,
                    'rank_to' => $rankTo,
                    'points' => $points,
                    'player' => $player,
                    'player_name' => $player->name,
                ];
            }

            $currentRank += $countInRound;
        }

        // 3. Fallback: Any registered players who haven't been ranked yet
        $unrankedPlayers = $tournament->players->filter(function ($p) use ($rankedPlayerIds) {
            return !in_array($p->id, $rankedPlayerIds);
        });

        if ($unrankedPlayers->isNotEmpty()) {
            $countUnranked = $unrankedPlayers->count();
            $rankFrom = $currentRank;
            $rankTo = $currentRank + $countUnranked - 1;
            $rankStr = ($countUnranked === 1)
                ? "{$rankFrom}."
                : "{$rankFrom}.–{$rankTo}.";
            $points = TournamentStanding::calculatePoints($rankFrom, $rankTo);

            foreach ($unrankedPlayers as $player) {
                $standings[] = [
                    'rank' => $rankStr,
                    'rank_num' => $rankFrom,
                    'rank_from' => $rankFrom,
                    'rank_to' => $rankTo,
                    'points' => $points,
                    'player' => $player,
                    'player_name' => $player->name,
                ];
            }
        }

        return $standings;
    }

    /**
     * Calculate standings for Single Elimination (Knockout) format.
     */
    protected function calculateKnockoutStandings(Tournament $tournament): array
    {
        $standings = [];
        $rankedPlayerIds = [];

        $rounds = $tournament->matches
            ->where('bracket_type', 'winners')
            ->groupBy('round')
            ->sortKeysDesc();

        $maxRound = $rounds->keys()->first();
        $finalMatch = $rounds->get($maxRound)?->first();

        if ($finalMatch && $finalMatch->status === 'completed' && $finalMatch->winner) {
            $standings[] = [
                'rank' => '1.',
                'rank_num' => 1,
                'rank_from' => 1,
                'rank_to' => 1,
                'points' => TournamentStanding::calculatePoints(1, 1),
                'player' => $finalMatch->winner,
                'player_name' => $finalMatch->winner->name,
            ];
            $rankedPlayerIds[] = $finalMatch->winner->id;

            if ($finalMatch->loser) {
                $standings[] = [
                    'rank' => '2.',
                    'rank_num' => 2,
                    'rank_from' => 2,
                    'rank_to' => 2,
                    'points' => TournamentStanding::calculatePoints(2, 2),
                    'player' => $finalMatch->loser,
                    'player_name' => $finalMatch->loser->name,
                ];
                $rankedPlayerIds[] = $finalMatch->loser->id;
            }
        }

        $currentRank = count($standings) + 1;

        foreach ($rounds as $roundNum => $matchesInRound) {
            if ($roundNum === $maxRound) {
                continue;
            }

            $eliminated = [];
            foreach ($matchesInRound as $m) {
                if ($m->status === 'completed' && $m->loser && !$m->is_bye) {
                    if (!in_array($m->loser->id, $rankedPlayerIds)) {
                        $eliminated[] = $m->loser;
                        $rankedPlayerIds[] = $m->loser->id;
                    }
                }
            }

            $count = count($eliminated);
            if ($count === 0) {
                continue;
            }

            $rankFrom = $currentRank;
            $rankTo = $currentRank + $count - 1;
            $rankStr = ($count === 1)
                ? "{$rankFrom}."
                : "{$rankFrom}.–{$rankTo}.";
            $points = TournamentStanding::calculatePoints($rankFrom, $rankTo);

            foreach ($eliminated as $player) {
                $standings[] = [
                    'rank' => $rankStr,
                    'rank_num' => $rankFrom,
                    'rank_from' => $rankFrom,
                    'rank_to' => $rankTo,
                    'points' => $points,
                    'player' => $player,
                    'player_name' => $player->name,
                ];
            }

            $currentRank += $count;
        }

        // Fallback: unranked registered players
        $unrankedPlayers = $tournament->players->filter(function ($p) use ($rankedPlayerIds) {
            return !in_array($p->id, $rankedPlayerIds);
        });

        if ($unrankedPlayers->isNotEmpty()) {
            $countUnranked = $unrankedPlayers->count();
            $rankFrom = $currentRank;
            $rankTo = $currentRank + $countUnranked - 1;
            $rankStr = ($countUnranked === 1)
                ? "{$rankFrom}."
                : "{$rankFrom}.–{$rankTo}.";
            $points = TournamentStanding::calculatePoints($rankFrom, $rankTo);

            foreach ($unrankedPlayers as $player) {
                $standings[] = [
                    'rank' => $rankStr,
                    'rank_num' => $rankFrom,
                    'rank_from' => $rankFrom,
                    'rank_to' => $rankTo,
                    'points' => $points,
                    'player' => $player,
                    'player_name' => $player->name,
                ];
            }
        }

        return $standings;
    }

    /**
     * Calculate and save standings transactionally into tournament_standings table.
     *
     * @param Tournament $tournament
     * @return Collection
     */
    public function calculateAndSaveStandings(Tournament $tournament): Collection
    {
        // Skip pure group or round robin formats
        if (in_array($tournament->format, ['groups', 'round_robin'])) {
            return new Collection();
        }

        return DB::transaction(function () use ($tournament) {
            // Idempotent delete of existing records
            $tournament->standings()->delete();

            $standings = $this->calculateStandings($tournament);
            $savedPlayerIds = [];

            foreach ($standings as $item) {
                $player = $item['player'] ?? null;
                if (!$player || in_array($player->id, $savedPlayerIds)) {
                    continue;
                }

                $rankFrom = (int) ($item['rank_from'] ?? $item['rank_num'] ?? 1);
                $rankTo = (int) ($item['rank_to'] ?? $rankFrom);
                $points = (int) ($item['points'] ?? TournamentStanding::calculatePoints($rankFrom, $rankTo));

                TournamentStanding::create([
                    'tournament_id' => $tournament->id,
                    'player_id' => $player->id,
                    'rank_from' => $rankFrom,
                    'rank_to' => $rankTo,
                    'points' => $points,
                ]);

                $savedPlayerIds[] = $player->id;
            }

            return $tournament->standings()->with('player')->get();
        });
    }

    public function generateFreeOnewayBracket(Tournament $tournament, int $size): void
    {
        if (!in_array($size, [8, 16, 32])) {
            $size = 8;
        }

        $tournament->update([
            'bracket_size' => $size,
            'is_seeding_finalized' => false,
            'status' => 'in_progress',
        ]);

        $K = (int) round(log($size, 2));

        // 1. Create mandatory 3rd/4th place match
        $thirdPlaceMatch = GameMatch::create([
            'tournament_id' => $tournament->id,
            'bracket_type' => 'third_place',
            'round' => 1,
            'round_name' => 'Zápas o 3. a 4. místo',
            'match_number' => 1,
            'status' => 'scheduled',
            'is_third_place' => true,
            'is_bye' => false,
        ]);

        // 2. Create Winners Bracket rounds 1 to K
        $matches = [];
        for ($r = 1; $r <= $K; $r++) {
            $count = (int) ($size / pow(2, $r));
            $dist = $K - $r;
            $roundName = match ($dist) {
                0 => 'Finále',
                1 => 'Semifinále',
                2 => 'Čtvrtfinále',
                3 => 'Osmifinále',
                4 => '1. kolo (32)',
                default => "Kolo {$r}",
            };

            $matches[$r] = [];
            for ($m = 1; $m <= $count; $m++) {
                $matches[$r][$m] = GameMatch::create([
                    'tournament_id' => $tournament->id,
                    'bracket_type' => 'winners',
                    'round' => $r,
                    'round_name' => $roundName,
                    'match_number' => $m,
                    'status' => 'scheduled',
                    'is_third_place' => false,
                    'is_bye' => false,
                ]);
            }
        }

        // 3. Link winner progressions between rounds
        for ($r = 1; $r < $K; $r++) {
            $count = count($matches[$r]);
            for ($m = 1; $m <= $count; $m++) {
                $currentMatch = $matches[$r][$m];
                $targetMatchNum = (int) ceil($m / 2);
                $slot = ($m % 2 === 1) ? 1 : 2;
                $currentMatch->next_match_id = $matches[$r + 1][$targetMatchNum]->id;
                $currentMatch->next_match_slot = $slot;
                $currentMatch->save();
            }
        }

        // 4. Link semifinal losers to 3rd place match!
        $semiRound = $K - 1;
        if (isset($matches[$semiRound]) && count($matches[$semiRound]) >= 2) {
            $semi1 = $matches[$semiRound][1];
            $semi1->loser_match_id = $thirdPlaceMatch->id;
            $semi1->loser_match_slot = 1;
            $semi1->save();

            $semi2 = $matches[$semiRound][2];
            $semi2->loser_match_id = $thirdPlaceMatch->id;
            $semi2->loser_match_slot = 2;
            $semi2->save();
        }
    }

    /**
     * Create an empty skeleton for FREE Double Elimination bracket (8, 16, or 32 positions)
     * with Winners Bracket (A), Losers Bracket (B), and Grand Finals with Reset match.
     *
     * @param Tournament $tournament
     * @param int $size
     * @return void
     */
    public function generateFreeDoubleBracket(Tournament $tournament, int $size): void
    {
        if (!in_array($size, [8, 16, 32])) {
            $size = 8;
        }

        $tournament->update([
            'bracket_size' => $size,
            'is_seeding_finalized' => false,
            'status' => 'in_progress',
        ]);

        $K = (int) round(log($size, 2)); // 3 for 8, 4 for 16, 5 for 32
        $L = 2 * ($K - 1);               // 4 for 8, 6 for 16, 8 for 32

        // 1. Create Grand Finals (GF1 and conditional GF2 Reset)
        $gf2 = GameMatch::create([
            'tournament_id' => $tournament->id,
            'bracket_type' => 'grand_final',
            'round' => 2,
            'round_name' => 'Grand Finále – Rozhodující zápas (Reset)',
            'match_number' => 2,
            'is_reset_match' => true,
            'status' => 'scheduled',
            'is_third_place' => false,
            'is_bye' => false,
        ]);

        $gf1 = GameMatch::create([
            'tournament_id' => $tournament->id,
            'bracket_type' => 'grand_final',
            'round' => 1,
            'round_name' => 'Grand Finále',
            'match_number' => 1,
            'is_reset_match' => false,
            'status' => 'scheduled',
            'is_third_place' => false,
            'is_bye' => false,
        ]);

        // 2. Create Losers Bracket (B)
        $lbMatches = [];
        for ($lr = 1; $lr <= $L; $lr++) {
            if ($lr === 1) {
                $count = (int) ($size / 4);
            } else {
                $prevCount = count($lbMatches[$lr - 1]);
                $count = ($lr % 2 === 0) ? $prevCount : (int) ($prevCount / 2);
            }

            $roundName = match ($lr) {
                $L => 'Finále B',
                $L - 1 => 'Semifinále B',
                default => "Kolo {$lr} (Větev B)",
            };

            $lbMatches[$lr] = [];
            for ($m = 1; $m <= $count; $m++) {
                $lbMatches[$lr][$m] = GameMatch::create([
                    'tournament_id' => $tournament->id,
                    'bracket_type' => 'losers',
                    'round' => $lr,
                    'round_name' => $roundName,
                    'match_number' => $m,
                    'status' => 'scheduled',
                    'is_third_place' => false,
                    'is_bye' => false,
                ]);
            }
        }

        // Link internal Losers Bracket winner progressions
        for ($lr = 1; $lr <= $L; $lr++) {
            $count = count($lbMatches[$lr]);
            for ($m = 1; $m <= $count; $m++) {
                $currentMatch = $lbMatches[$lr][$m];

                if ($lr < $L) {
                    if ($lr % 2 === 1) {
                        // Odd round winner -> moves to same match number, slot 1 in next round
                        $currentMatch->next_match_id = $lbMatches[$lr + 1][$m]->id;
                        $currentMatch->next_match_slot = 1;
                    } else {
                        // Even round winner -> moves to half match number in next round
                        $targetMatchNum = (int) ceil($m / 2);
                        $slot = ($m % 2 === 1) ? 1 : 2;
                        $currentMatch->next_match_id = $lbMatches[$lr + 1][$targetMatchNum]->id;
                        $currentMatch->next_match_slot = $slot;
                    }
                } else {
                    // Losers Final winner -> moves to Grand Final 1, slot 2!
                    $currentMatch->next_match_id = $gf1->id;
                    $currentMatch->next_match_slot = 2;
                }
                $currentMatch->save();
            }
        }

        // 3. Create Winners Bracket (A)
        $wbMatches = [];
        for ($wr = 1; $wr <= $K; $wr++) {
            $count = (int) ($size / pow(2, $wr));

            $dist = $K - $wr;
            $roundName = match ($dist) {
                0 => 'Finále A',
                1 => 'Semifinále A',
                2 => 'Čtvrtfinále A',
                3 => 'Osmifinále A',
                4 => '1. kolo (32) A',
                default => "Kolo {$wr} (Větev A)",
            };

            $wbMatches[$wr] = [];
            for ($m = 1; $m <= $count; $m++) {
                $wbMatches[$wr][$m] = GameMatch::create([
                    'tournament_id' => $tournament->id,
                    'bracket_type' => 'winners',
                    'round' => $wr,
                    'round_name' => $roundName,
                    'match_number' => $m,
                    'status' => 'scheduled',
                    'is_third_place' => false,
                    'is_bye' => false,
                ]);
            }
        }

        // Link internal Winners Bracket winner progressions & loser drops to Losers Bracket
        for ($wr = 1; $wr <= $K; $wr++) {
            $count = count($wbMatches[$wr]);
            for ($m = 1; $m <= $count; $m++) {
                $currentMatch = $wbMatches[$wr][$m];

                if ($wr < $K) {
                    $targetMatchNum = (int) ceil($m / 2);
                    $slot = ($m % 2 === 1) ? 1 : 2;
                    $currentMatch->next_match_id = $wbMatches[$wr + 1][$targetMatchNum]->id;
                    $currentMatch->next_match_slot = $slot;
                } else {
                    // Winners Final winner -> moves to Grand Final 1, slot 1!
                    $currentMatch->next_match_id = $gf1->id;
                    $currentMatch->next_match_slot = 1;
                }

                // Link losers dropping into Losers Bracket
                if ($wr === 1) {
                    // Losers from WB Round 1 drop into LB Round 1
                    $targetMatchNum = (int) ceil($m / 2);
                    $slot = ($m % 2 === 1) ? 1 : 2;
                    $currentMatch->loser_match_id = $lbMatches[1][$targetMatchNum]->id;
                    $currentMatch->loser_match_slot = $slot;
                } elseif ($wr === $K) {
                    // Loser from Winners Final drops into Losers Final (Round L), slot 2
                    $currentMatch->loser_match_id = $lbMatches[$L][1]->id;
                    $currentMatch->loser_match_slot = 2;
                } else {
                    // Losers from WB intermediate rounds drop into LB even rounds
                    $targetLbRound = 2 * ($wr - 1);
                    $lbRoundCount = count($lbMatches[$targetLbRound]);
                    // Cross pair to avoid immediate rematch
                    $targetMatchNum = $lbRoundCount - $m + 1;
                    $currentMatch->loser_match_id = $lbMatches[$targetLbRound][$targetMatchNum]->id;
                    $currentMatch->loser_match_slot = 2;
                }

                $currentMatch->save();
            }
        }
    }

    /**
     * Assign or clear a player in a Round 1 match slot.
     *
     * @param Tournament $tournament
     * @param int $matchId
     * @param int $slot
     * @param int|string|null $playerId
     * @return array
     */
    public function assignSlot(Tournament $tournament, int $matchId, int $slot, int|string|null $playerId): array
    {
        $freeFormats = ['free_oneway_elimination', 'free_double_elimination'];
        if (!in_array($tournament->format, $freeFormats)) {
            throw new \InvalidArgumentException("Nasazování slotů je povoleno pouze pro turnaje typu FREE Elimination.");
        }

        if ($tournament->is_seeding_finalized) {
            throw new \InvalidArgumentException("Nasazení v tomto turnaji již bylo uzavřeno a nelze jej měnit.");
        }

        if (!in_array($slot, [1, 2])) {
            throw new \InvalidArgumentException("Číslo slotu musí být 1 nebo 2.");
        }

        $match = $tournament->matches()
            ->where('id', $matchId)
            ->where('bracket_type', 'winners')
            ->where('round', 1)
            ->firstOrFail();

        if ($playerId) {
            $player = is_numeric($playerId)
                ? Player::find($playerId)
                : Player::firstOrCreate(['name' => trim($playerId)], ['gender' => 'male']);

            if (!$player) {
                throw new \InvalidArgumentException("Hráč nebyl nalezen.");
            }

            $playerId = $player->id;

            // Zkontrolujeme, zda hráč již není nasazen v jiném slotu tohoto turnaje
            $alreadyAssignedMatch = $tournament->matches()
                ->where('bracket_type', 'winners')
                ->where('round', 1)
                ->where(function ($q) use ($playerId) {
                    $q->where('player1_id', $playerId)->orWhere('player2_id', $playerId);
                })
                ->where(function ($q) use ($matchId, $slot, $playerId) {
                    $q->where('id', '!=', $matchId)
                      ->orWhere($slot === 1 ? 'player2_id' : 'player1_id', $playerId);
                })
                ->first();

            if ($alreadyAssignedMatch) {
                throw new \InvalidArgumentException("Hráč {$player->name} je již v tomto pavouku nasazen.");
            }

            if ($slot === 1) {
                $match->player1_id = $playerId;
            } else {
                $match->player2_id = $playerId;
            }
        } else {
            if ($slot === 1) {
                $match->player1_id = null;
            } else {
                $match->player2_id = null;
            }
        }

        $match->save();

        return $this->getSeedingStatus($tournament);
    }

    /**
     * Get real-time status of seeding validation for FREE Elimination.
     *
     * @param Tournament $tournament
     * @return array
     */
    public function getSeedingStatus(Tournament $tournament): array
    {
        $size = $tournament->bracket_size ?: 8;
        $minPlayers = (int) ($size / 2) + 1; // 5 pro 8, 9 pro 16, 17 pro 32

        $r1Matches = $tournament->matches()
            ->where('bracket_type', 'winners')
            ->where('round', 1)
            ->orderBy('match_number')
            ->get();
        $assignedPlayerIds = [];
        $emptyMatchesCount = 0;

        foreach ($r1Matches as $m) {
            if ($m->player1_id) $assignedPlayerIds[] = $m->player1_id;
            if ($m->player2_id) $assignedPlayerIds[] = $m->player2_id;
            if (!$m->player1_id && !$m->player2_id) {
                $emptyMatchesCount++;
            }
        }

        $assignedCount = count($assignedPlayerIds);
        $hasEnoughPlayers = $assignedCount >= $minPlayers;
        $hasNoEmptyMatches = $emptyMatchesCount === 0;
        $canFinalize = $hasEnoughPlayers && $hasNoEmptyMatches;

        $errors = [];
        if (!$hasEnoughPlayers) {
            $errors[] = "V {$size}členném pavouku musí být nasazeno alespoň {$minPlayers} hráčů (aktuálně {$assignedCount}).";
        }
        if (!$hasNoEmptyMatches) {
            $errors[] = "Žádný zápas 1. kola nesmí mít oba sloty prázdné (aktuálně {$emptyMatchesCount} zcela prázdných zápasů).";
        }

        return [
            'bracket_size' => $size,
            'min_players' => $minPlayers,
            'assigned_count' => $assignedCount,
            'assigned_player_ids' => $assignedPlayerIds,
            'empty_matches_count' => $emptyMatchesCount,
            'can_finalize' => $canFinalize,
            'errors' => $errors,
        ];
    }

    /**
     * Finalize seeding for FREE Elimination bracket, evaluate BYEs, and start the tournament.
     *
     * @param Tournament $tournament
     * @return array
     */
    public function finalizeFreeSeeding(Tournament $tournament): array
    {
        $freeFormats = ['free_oneway_elimination', 'free_double_elimination'];
        if (!in_array($tournament->format, $freeFormats)) {
            throw new \InvalidArgumentException("Uzavření nasazení je určeno pouze pro turnaje FREE Elimination.");
        }

        if ($tournament->is_seeding_finalized) {
            return ['success' => true, 'message' => 'Nasazení již bylo dříve uzavřeno.'];
        }

        $status = $this->getSeedingStatus($tournament);
        if (!$status['can_finalize']) {
            throw new \InvalidArgumentException(implode(' ', $status['errors']));
        }

        $r1Matches = $tournament->matches()
            ->where('bracket_type', 'winners')
            ->where('round', 1)
            ->orderBy('match_number')
            ->get();

        foreach ($r1Matches as $match) {
            $p1 = $match->player1_id;
            $p2 = $match->player2_id;

            if ($p1 && !$p2) {
                // Hráč 1 vs BYE -> automatická výhra a postup
                $match->update([
                    'is_bye' => true,
                    'status' => 'completed',
                    'winner_id' => $p1,
                    'loser_id' => null,
                    'player1_legs' => 0,
                    'player2_legs' => 0,
                ]);

                if ($match->next_match_id) {
                    $next = GameMatch::find($match->next_match_id);
                    if ($next) {
                        if ($match->next_match_slot === 1) $next->player1_id = $p1;
                        else $next->player2_id = $p1;
                        $next->save();
                    }
                }
            } elseif (!$p1 && $p2) {
                // BYE vs Hráč 2 -> automatická výhra a postup
                $match->update([
                    'is_bye' => true,
                    'status' => 'completed',
                    'winner_id' => $p2,
                    'loser_id' => null,
                    'player1_legs' => 0,
                    'player2_legs' => 0,
                ]);

                if ($match->next_match_id) {
                    $next = GameMatch::find($match->next_match_id);
                    if ($next) {
                        if ($match->next_match_slot === 1) $next->player1_id = $p2;
                        else $next->player2_id = $p2;
                        $next->save();
                    }
                }
            } else {
                // Oba hráči přítomni -> reálný zápas
                $match->update([
                    'is_bye' => false,
                    'status' => 'scheduled',
                ]);
            }
        }

        // Uzamkneme nasazení a propojíme hráče v pivotu
        $tournament->update([
            'is_seeding_finalized' => true,
            'status' => 'in_progress',
        ]);

        $tournament->players()->sync($status['assigned_player_ids']);

        // Kaskádovité vyhodnocení BYE pro downstream zápasy
        $tournament->matches()->get()->each(function (GameMatch $match) {
            $match->checkAndAdvanceBye();
        });

        $tournament->recalculateStatus();

        return [
            'success' => true,
            'message' => 'Nasazení bylo úspěšně uzavřeno. Volné losy byly vyhodnoceny a turnaj je připraven ke hře!',
        ];
    }
}
