<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Tournament extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'date',
        'status',
        'game_mode',
        'checkout_mode',
        'format',
        'counts_for_ranking',
        'season_year',
        'bracket_size',
        'is_seeding_finalized',
        'best_of_legs',
        'winner_id',
        'second_place_id',
        'third_place_id',
    ];

    protected $casts = [
        'date' => 'date',
        'counts_for_ranking' => 'boolean',
        'season_year' => 'integer',
        'best_of_legs' => 'integer',
        'bracket_size' => 'integer',
        'is_seeding_finalized' => 'boolean',
    ];

    protected static function booted()
    {
        static::creating(function ($tournament) {
            if (empty($tournament->date)) {
                $tournament->date = now()->toDateString();
            }
            if (empty($tournament->season_year)) {
                $tournament->season_year = !empty($tournament->date) ? (int) date('Y', strtotime($tournament->date)) : (int) date('Y');
            }
            if (!isset($tournament->counts_for_ranking)) {
                $tournament->counts_for_ranking = true;
            }
        });
    }

    /**
     * Scope a query to only include tournaments counted for ranking.
     */
    public function scopeCountedForRanking($query)
    {
        return $query->where('counts_for_ranking', true);
    }

    /**
     * Scope a query to only include tournaments for a specific season year.
     */
    public function scopeForSeason($query, int $year)
    {
        return $query->where('season_year', $year);
    }

    public function winner(): BelongsTo
    {
        return $this->belongsTo(Player::class, 'winner_id');
    }

    public function secondPlace(): BelongsTo
    {
        return $this->belongsTo(Player::class, 'second_place_id');
    }

    public function thirdPlace(): BelongsTo
    {
        return $this->belongsTo(Player::class, 'third_place_id');
    }

    public function thirdPlaceMatch(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(GameMatch::class)
            ->where(function ($q) {
                $q->where('is_third_place', true)
                  ->orWhere('bracket_type', 'third_place');
            });
    }

    public function getTypeAttribute(): string
    {
        return $this->format ?? 'double_elimination';
    }

    public function setTypeAttribute($value): void
    {
        $this->attributes['format'] = $value;
    }

    public function players(): BelongsToMany
    {
        return $this->belongsToMany(Player::class, 'tournament_players')
            ->withPivot('seed')
            ->withTimestamps();
    }

    public function matches(): HasMany
    {
        return $this->hasMany(GameMatch::class)->orderBy('bracket_type')->orderBy('round')->orderBy('match_number');
    }

    public function winnersMatches(): HasMany
    {
        return $this->hasMany(GameMatch::class)
            ->where('bracket_type', 'winners')
            ->orderBy('round')
            ->orderBy('match_number');
    }

    public function losersMatches(): HasMany
    {
        return $this->hasMany(GameMatch::class)
            ->where('bracket_type', 'losers')
            ->orderBy('round')
            ->orderBy('match_number');
    }

    public function grandFinalMatches(): HasMany
    {
        return $this->hasMany(GameMatch::class)
            ->where('bracket_type', 'grand_final')
            ->orderBy('match_number');
    }

    public function groups(): HasMany
    {
        return $this->hasMany(TournamentGroup::class)->orderBy('order');
    }

    public function groupMatches(): HasMany
    {
        return $this->hasMany(GameMatch::class)
            ->whereNotNull('tournament_group_id')
            ->orderBy('round')
            ->orderBy('match_number');
    }

    public function standings(): HasMany
    {
        return $this->hasMany(TournamentStanding::class)->orderBy('rank_from');
    }

    public function getStatusBadgeClassAttribute(): string
    {
        return match ($this->status) {
            'finished' => 'badge-gradient-success',
            'ongoing', 'in_progress' => 'badge-gradient-warning',
            default => 'badge-gradient-info',
        };
    }

    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            'finished' => 'Ukončen',
            'ongoing', 'in_progress' => 'Probíhá',
            default => 'Příprava (Draft)',
        };
    }

    public function getFormatLabelAttribute(): string
    {
        return match ($this->format) {
            'free_oneway_elimination' => 'FREE Oneway Elimination (Single + o 3. místo)',
            'free_double_elimination' => 'FREE Double Elimination (s Pavoukem B a Finále)',
            'double_elimination' => 'Double Elimination (Pavouk A, B a Finále)',
            'knockout', 'single_elimination' => 'Single Elimination (KO pavouk)',
            'round_robin', 'groups' => 'Každý s každým (Skupiny)',
            default => $this->format,
        };
    }

    /**
     * Find third place player from the losers final match
     */
    public function calculateThirdPlace(): ?Player
    {
        $losersFinal = $this->matches()
            ->reorder()
            ->where('bracket_type', 'losers')
            ->orderByDesc('round')
            ->first();

        return $losersFinal?->loser;
    }

    /**
     * Calculate standings for this tournament.
     *
     * @return array
     */
    public function calculateStandings(): array
    {
        return (new \App\Services\TournamentService())->calculateStandings($this);
    }

    /**
     * Recalculate tournament status, winner, 2nd and 3rd place from current match results.
     */
    public function recalculateStatus(): void
    {
        $this->loadMissing(['matches.player1', 'matches.player2', 'matches.winner', 'matches.loser']);

        if (in_array($this->format, ['double_elimination', 'free_double_elimination'])) {
            $ongoingStatus = ($this->format === 'double_elimination') ? 'ongoing' : 'in_progress';

            if ($this->format === 'free_double_elimination' && !$this->is_seeding_finalized) {
                $this->update([
                    'status' => 'in_progress',
                    'winner_id' => null,
                    'second_place_id' => null,
                    'third_place_id' => null,
                ]);
                $this->standings()->delete();
                return;
            }

            $gfMatches = $this->matches->where('bracket_type', 'grand_final')->sortBy('match_number');
            $gf1 = $gfMatches->where('is_reset_match', false)->first();
            $gf2 = $gfMatches->where('is_reset_match', true)->first();

            if (!$gf1 || $gf1->status !== 'completed' || !$gf1->winner_id) {
                $this->update([
                    'status' => $ongoingStatus,
                    'winner_id' => null,
                    'second_place_id' => null,
                    'third_place_id' => null,
                ]);
                $this->standings()->delete();
                return;
            }

            // GF1 was completed
            if ($gf1->winner_id === $gf1->player1_id) {
                // Winner from A won GF1 -> Finished
                $third = $this->calculateThirdPlace();
                $this->update([
                    'status' => 'finished',
                    'winner_id' => $gf1->player1_id,
                    'second_place_id' => $gf1->player2_id,
                    'third_place_id' => $third?->id,
                ]);
                (new \App\Services\TournamentService())->calculateAndSaveStandings($this);
            } else {
                // Winner from B won GF1 -> Needs GF2 (Reset match)
                if ($gf2 && $gf2->status === 'completed' && $gf2->winner_id) {
                    $third = $this->calculateThirdPlace();
                    $this->update([
                        'status' => 'finished',
                        'winner_id' => $gf2->winner_id,
                        'second_place_id' => $gf2->loser_id,
                        'third_place_id' => $third?->id,
                    ]);
                    (new \App\Services\TournamentService())->calculateAndSaveStandings($this);
                } else {
                    $this->update([
                        'status' => $ongoingStatus,
                        'winner_id' => null,
                        'second_place_id' => null,
                        'third_place_id' => null,
                    ]);
                    $this->standings()->delete();
                }
            }
        } elseif ($this->format === 'free_oneway_elimination') {
            if (!$this->is_seeding_finalized) {
                $this->update([
                    'status' => 'in_progress',
                    'winner_id' => null,
                    'second_place_id' => null,
                    'third_place_id' => null,
                ]);
                $this->standings()->delete();
                return;
            }

            $finalMatch = $this->matches->where('bracket_type', 'winners')->sortByDesc('round')->first();
            $thirdMatch = $this->thirdPlaceMatch;

            $finalCompleted = ($finalMatch && $finalMatch->status === 'completed' && $finalMatch->winner_id);
            $thirdCompleted = ($thirdMatch && $thirdMatch->status === 'completed' && $thirdMatch->winner_id);

            if ($finalCompleted && $thirdCompleted) {
                $this->update([
                    'status' => 'finished',
                    'winner_id' => $finalMatch->winner_id,
                    'second_place_id' => $finalMatch->loser_id,
                    'third_place_id' => $thirdMatch->winner_id,
                ]);
                (new \App\Services\TournamentService())->calculateAndSaveStandings($this);
            } else {
                $this->update([
                    'status' => in_array($this->status, ['in_progress', 'ongoing']) ? $this->status : 'in_progress',
                    'winner_id' => null,
                    'second_place_id' => null,
                    'third_place_id' => null,
                ]);
                $this->standings()->delete();
            }
        } else {
            // Knockout / Single Elimination
            $finalMatch = $this->matches->where('bracket_type', 'winners')->sortByDesc('round')->first();
            if ($finalMatch && $finalMatch->status === 'completed' && $finalMatch->winner_id) {
                $this->update([
                    'status' => 'finished',
                    'winner_id' => $finalMatch->winner_id,
                    'second_place_id' => $finalMatch->loser_id,
                ]);
                (new \App\Services\TournamentService())->calculateAndSaveStandings($this);
            } else {
                $this->update([
                    'status' => in_array($this->status, ['in_progress', 'ongoing']) ? $this->status : 'in_progress',
                    'winner_id' => null,
                    'second_place_id' => null,
                ]);
                $this->standings()->delete();
            }
        }
    }

    /**
     * Create an empty skeleton for FREE Oneway Elimination bracket with mandatory 3rd/4th place match.
     */
    public function generateFreeOnewayBracket(int $size): void
    {
        (new \App\Services\TournamentService())->generateFreeOnewayBracket($this, $size);
    }

    /**
     * Create an empty skeleton for FREE Double Elimination bracket with WB, LB, and Grand Finals.
     */
    public function generateFreeDoubleBracket(int $size): void
    {
        (new \App\Services\TournamentService())->generateFreeDoubleBracket($this, $size);
    }

    /**
     * Assign or clear a player in a Round 1 match slot.
     */
    public function assignSlot(int $matchId, int $slot, ?int $playerId): array
    {
        return (new \App\Services\TournamentService())->assignSlot($this, $matchId, $slot, $playerId);
    }

    /**
     * Finalize seeding for FREE formats (evaluate BYEs, lock slots, start tournament).
     */
    public function finalizeFreeSeeding(): array
    {
        return (new \App\Services\TournamentService())->finalizeFreeSeeding($this);
    }

    /**
     * Finalize seeding for FREE Elimination bracket (alias).
     */
    public function finalizeFreeEliminationSeeding(): array
    {
        return $this->finalizeFreeSeeding();
    }

    /**
     * Generate complete Double Elimination bracket (Winners Bracket A, Losers Bracket B, Grand Finals)
     */
    public function generateDoubleEliminationMatches(array $playerIds): void
    {
        $numPlayers = count($playerIds);
        if ($numPlayers < 2) {
            return;
        }

        // Bracket size: 4, 8, 16, 32
        $bracketSize = 4;
        while ($bracketSize < $numPlayers) {
            $bracketSize *= 2;
        }

        $K = (int) round(log($bracketSize, 2)); // 2 for 4, 3 for 8, 4 for 16, 5 for 32
        $L = 2 * ($K - 1);                     // Total rounds in Losers Bracket (2, 4, 6, 8)

        // 1. Create Grand Finals
        $gf2 = GameMatch::create([
            'tournament_id' => $this->id,
            'bracket_type' => 'grand_final',
            'round' => 2,
            'round_name' => 'Grand Finále – Rozhodující zápas (Reset)',
            'match_number' => 2,
            'is_reset_match' => true,
            'status' => 'scheduled',
        ]);

        $gf1 = GameMatch::create([
            'tournament_id' => $this->id,
            'bracket_type' => 'grand_final',
            'round' => 1,
            'round_name' => 'Grand Finále',
            'match_number' => 1,
            'is_reset_match' => false,
            'status' => 'scheduled',
        ]);

        // 2. Create Losers Bracket (B)
        $lbMatches = [];
        for ($lr = 1; $lr <= $L; $lr++) {
            // Determine match count for Losers Round lr
            if ($lr === 1) {
                $count = (int) ($bracketSize / 4);
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
                    'tournament_id' => $this->id,
                    'bracket_type' => 'losers',
                    'round' => $lr,
                    'round_name' => $roundName,
                    'match_number' => $m,
                    'status' => 'scheduled',
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
            $count = (int) ($bracketSize / pow(2, $wr));

            $dist = $K - $wr;
            $roundName = match ($dist) {
                0 => 'Finále A',
                1 => 'Semifinále A',
                2 => 'Čtvrtfinále A',
                3 => 'Osmifinále A',
                default => "Kolo {$wr} (Větev A)",
            };

            $wbMatches[$wr] = [];
            for ($m = 1; $m <= $count; $m++) {
                $wbMatches[$wr][$m] = GameMatch::create([
                    'tournament_id' => $this->id,
                    'bracket_type' => 'winners',
                    'round' => $wr,
                    'round_name' => $roundName,
                    'match_number' => $m,
                    'status' => 'scheduled',
                ]);
            }
        }

        // Link internal Winners Bracket winner progressions
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

        // 4. Assign Players & BYEs to Winners Bracket Round 1
        $r1Count = count($wbMatches[1]);
        $numByes = $bracketSize - $numPlayers;

        // Select matches that receive BYEs (evenly distributed across the bracket)
        $byeMatchIndices = [];
        if ($numByes > 0) {
            for ($i = 0; $i < $numByes; $i++) {
                $idx = (int) floor($i * $r1Count / $numByes) + 1;
                while (in_array($idx, $byeMatchIndices) && $idx <= $r1Count) {
                    $idx++;
                }
                if ($idx > $r1Count) {
                    for ($k = 1; $k <= $r1Count; $k++) {
                        if (!in_array($k, $byeMatchIndices)) {
                            $idx = $k;
                            break;
                        }
                    }
                }
                $byeMatchIndices[] = $idx;
            }
        }

        $playersQueue = $playerIds;

        // Step A: Every match gets player 1 (guaranteed at least 1 player per match)
        for ($m = 1; $m <= $r1Count; $m++) {
            $match = $wbMatches[1][$m];
            $match->player1_id = array_shift($playersQueue);
            $match->save();
        }

        // Step B: Matches not in $byeMatchIndices get player 2
        for ($m = 1; $m <= $r1Count; $m++) {
            $match = $wbMatches[1][$m];
            if (in_array($m, $byeMatchIndices)) {
                $match->player2_id = null;
                $match->is_bye = true;
            } else {
                $match->player2_id = array_shift($playersQueue);
                $match->is_bye = false;
            }
            $match->save();
        }

        // 5. Automatically evaluate Round 1 BYE matches and advance winners
        for ($m = 1; $m <= $r1Count; $m++) {
            $match = $wbMatches[1][$m];
            if ($match->is_bye) {
                $match->update([
                    'status' => 'completed',
                    'winner_id' => $match->player1_id,
                    'loser_id' => null,
                    'player1_legs' => 0,
                    'player2_legs' => 0,
                ]);

                // Advance winner to next match in Winners Bracket
                if ($match->next_match_id) {
                    $next = GameMatch::find($match->next_match_id);
                    if ($next) {
                        if ($match->next_match_slot === 1) {
                            $next->player1_id = $match->winner_id;
                        } else {
                            $next->player2_id = $match->winner_id;
                        }
                        $next->save();
                    }
                }

                // DŮLEŽITÉ: Do Losers Bracketu se v případě BYE nepropisuje žádná porážka ani prázdný záznam!
            }
        }

        // 6. Check downstream matches for any cascading BYE resolution
        $this->matches()->get()->each(function (GameMatch $match) {
            $match->checkAndAdvanceBye();
        });
    }
}
