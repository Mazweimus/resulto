<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GameMatch extends Model
{
    use HasFactory;

    protected $fillable = [
        'tournament_id',
        'tournament_group_id',
        'bracket_type', // 'winners', 'losers', 'grand_final', 'group'
        'round',
        'round_name',
        'match_number',
        'player1_id',
        'player2_id',
        'winner_id',
        'loser_id',
        'player1_legs',
        'player2_legs',
        'status', // 'scheduled', 'completed'
        'next_match_id',
        'next_match_slot',
        'loser_match_id',
        'loser_match_slot',
        'is_reset_match',
        'is_third_place',
        'is_bye',
    ];

    protected $casts = [
        'round' => 'integer',
        'match_number' => 'integer',
        'player1_legs' => 'integer',
        'player2_legs' => 'integer',
        'is_reset_match' => 'boolean',
        'is_third_place' => 'boolean',
        'is_bye' => 'boolean',
    ];

    public function tournament(): BelongsTo
    {
        return $this->belongsTo(Tournament::class);
    }

    public function tournamentGroup(): BelongsTo
    {
        return $this->belongsTo(TournamentGroup::class, 'tournament_group_id');
    }

    public function group(): BelongsTo
    {
        return $this->tournamentGroup();
    }

    public function player1(): BelongsTo
    {
        return $this->belongsTo(Player::class, 'player1_id');
    }

    public function player2(): BelongsTo
    {
        return $this->belongsTo(Player::class, 'player2_id');
    }

    public function winner(): BelongsTo
    {
        return $this->belongsTo(Player::class, 'winner_id');
    }

    public function loser(): BelongsTo
    {
        return $this->belongsTo(Player::class, 'loser_id');
    }

    public function nextMatch(): BelongsTo
    {
        return $this->belongsTo(GameMatch::class, 'next_match_id');
    }

    public function loserMatch(): BelongsTo
    {
        return $this->belongsTo(GameMatch::class, 'loser_match_id');
    }

    public function getStatusBadgeClassAttribute(): string
    {
        if ($this->is_bye) {
            return 'bg-info text-white';
        }

        return match ($this->status) {
            'completed' => 'bg-success text-white',
            'in_progress' => 'bg-warning text-dark',
            'pending', 'scheduled' => 'bg-secondary text-white',
            default => 'bg-secondary text-white',
        };
    }

    public function getStatusLabelAttribute(): string
    {
        if ($this->is_bye) {
            return 'Volný los (Postup)';
        }

        return match ($this->status) {
            'completed' => 'Odehráno',
            'in_progress' => 'Právě se hraje',
            'pending', 'scheduled' => 'Připravuje se',
            default => 'Připravuje se',
        };
    }

    /**
     * Check if a specific slot (1 or 2) in this match is permanently empty
     * because its feeder was a BYE (produced no participant) or has no feeder in Round 1.
     */
    public function isSlotPermanentBye(int $slot): bool
    {
        if ($slot === 1 && $this->player1_id) {
            return false;
        }
        if ($slot === 2 && $this->player2_id) {
            return false;
        }

        // Check if there is a match feeding this slot as winner
        $winnerFeeder = GameMatch::where('tournament_id', $this->tournament_id)
            ->where('next_match_id', $this->id)
            ->where('next_match_slot', $slot)
            ->first();

        if ($winnerFeeder) {
            if ($winnerFeeder->status === 'completed' && !$winnerFeeder->winner_id) {
                return true;
            }
            return false;
        }

        // Check if there is a match feeding this slot as loser
        $loserFeeder = GameMatch::where('tournament_id', $this->tournament_id)
            ->where('loser_match_id', $this->id)
            ->where('loser_match_slot', $slot)
            ->first();

        if ($loserFeeder) {
            // If the feeder match completed without a loser (e.g. it was a BYE match)
            if ($loserFeeder->status === 'completed' && !$loserFeeder->loser_id) {
                return true;
            }
            return false;
        }

        // If no match feeds this slot and we are in Round 1 of Winners Bracket, it's a BYE
        if ($this->bracket_type === 'winners' && $this->round === 1) {
            return true;
        }

        return false;
    }

    /**
     * Check if one of the slots in this match is permanently empty due to a BYE in feeding matches,
     * and if so, automatically complete this match as a BYE and advance the single player.
     */
    public function checkAndAdvanceBye(): void
    {
        if ($this->status === 'completed' || $this->is_reset_match) {
            return;
        }

        $p1Bye = $this->isSlotPermanentBye(1);
        $p2Bye = $this->isSlotPermanentBye(2);

        // Case 1: Player 1 is present, Slot 2 is permanent BYE
        if ($this->player1_id && $p2Bye) {
            $this->update([
                'is_bye' => true,
                'status' => 'completed',
                'winner_id' => $this->player1_id,
                'loser_id' => null,
                'player1_legs' => 0,
                'player2_legs' => 0,
            ]);
            $this->advanceParticipants();
            return;
        }

        // Case 2: Player 2 is present, Slot 1 is permanent BYE
        if ($this->player2_id && $p1Bye) {
            $this->update([
                'is_bye' => true,
                'status' => 'completed',
                'winner_id' => $this->player2_id,
                'loser_id' => null,
                'player1_legs' => 0,
                'player2_legs' => 0,
            ]);
            $this->advanceParticipants();
            return;
        }

        // Case 3: Both slots are permanent BYEs (no real players)
        if ($p1Bye && $p2Bye && !$this->player1_id && !$this->player2_id) {
            $this->update([
                'is_bye' => true,
                'status' => 'completed',
                'winner_id' => null,
                'loser_id' => null,
                'player1_legs' => 0,
                'player2_legs' => 0,
            ]);
            if ($this->next_match_id) {
                $next = GameMatch::find($this->next_match_id);
                $next?->checkAndAdvanceBye();
            }
            return;
        }
    }

    /**
     * When this match's winner/loser changes or resets, cascade the changes to downstream matches.
     */
    public function cascadeClearDownstream(?int $oldWinnerId, ?int $oldLoserId): void
    {
        // 1. Next match (winner path)
        if ($this->next_match_id && $oldWinnerId) {
            $next = GameMatch::find($this->next_match_id);
            if ($next) {
                $affected = false;
                if ($this->next_match_slot === 1 && $next->player1_id === $oldWinnerId) {
                    $next->player1_id = null;
                    $affected = true;
                } elseif ($this->next_match_slot === 2 && $next->player2_id === $oldWinnerId) {
                    $next->player2_id = null;
                    $affected = true;
                }

                if ($affected) {
                    $prevNextWinner = $next->winner_id;
                    $prevNextLoser = $next->loser_id;
                    $next->update([
                        'status' => 'scheduled',
                        'player1_legs' => 0,
                        'player2_legs' => 0,
                        'winner_id' => null,
                        'loser_id' => null,
                    ]);
                    $next->cascadeClearDownstream($prevNextWinner, $prevNextLoser);
                }
            }
        }

        // 2. Loser target (loser path)
        if ($this->loser_match_id && $oldLoserId) {
            $loserTarget = GameMatch::find($this->loser_match_id);
            if ($loserTarget) {
                $affected = false;
                if ($this->loser_match_slot === 1 && $loserTarget->player1_id === $oldLoserId) {
                    $loserTarget->player1_id = null;
                    $affected = true;
                } elseif ($this->loser_match_slot === 2 && $loserTarget->player2_id === $oldLoserId) {
                    $loserTarget->player2_id = null;
                    $affected = true;
                }

                if ($affected) {
                    $prevLoserTargetWinner = $loserTarget->winner_id;
                    $prevLoserTargetLoser = $loserTarget->loser_id;
                    $loserTarget->update([
                        'status' => 'scheduled',
                        'player1_legs' => 0,
                        'player2_legs' => 0,
                        'winner_id' => null,
                        'loser_id' => null,
                    ]);
                    $loserTarget->cascadeClearDownstream($prevLoserTargetWinner, $prevLoserTargetLoser);
                }
            }
        }

        // 3. Grand Final 2 (Reset match) handling
        if ($this->bracket_type === 'grand_final' && !$this->is_reset_match && $this->tournament) {
            $gf2 = $this->tournament->matches()
                ->where('bracket_type', 'grand_final')
                ->where('is_reset_match', true)
                ->first();

            if ($gf2) {
                $gf2->update([
                    'player1_id' => null,
                    'player2_id' => null,
                    'player1_legs' => 0,
                    'player2_legs' => 0,
                    'winner_id' => null,
                    'loser_id' => null,
                    'status' => 'scheduled',
                ]);
            }
        }
    }

    /**
     * Advance winner and loser through the double elimination bracket.
     */
    public function advanceParticipants(): void
    {
        if (!$this->winner_id) {
            return;
        }

        // 1. Advance winner to next match in bracket
        if ($this->next_match_id) {
            $next = GameMatch::find($this->next_match_id);
            if ($next) {
                if ($this->next_match_slot === 1) {
                    $next->player1_id = $this->winner_id;
                } else {
                    $next->player2_id = $this->winner_id;
                }
                $next->save();
                $next->checkAndAdvanceBye();
            }
        }

        // 2. Drop loser to losers bracket (ONLY if a real loser exists)
        if ($this->loser_match_id && $this->loser_id) {
            $loserTarget = GameMatch::find($this->loser_match_id);
            if ($loserTarget) {
                if ($this->loser_match_slot === 1) {
                    $loserTarget->player1_id = $this->loser_id;
                } else {
                    $loserTarget->player2_id = $this->loser_id;
                }
                $loserTarget->save();
                $loserTarget->checkAndAdvanceBye();
            }
        }

        // 3. Grand Final Handling
        if ($this->bracket_type === 'grand_final') {
            $tournament = $this->tournament;
            if (!$tournament) {
                return;
            }

            if (!$this->is_reset_match) {
                // Grand Final 1:
                // Player 1 comes from Winners Bracket (0 previous losses)
                // Player 2 comes from Losers Bracket (1 previous loss)
                if ($this->winner_id === $this->player1_id) {
                    // Winner from A wins -> tournament is completed immediately
                    $thirdPlace = $tournament->calculateThirdPlace();

                    $tournament->update([
                        'winner_id' => $this->player1_id,
                        'second_place_id' => $this->player2_id,
                        'third_place_id' => $thirdPlace?->id,
                        'status' => 'finished',
                    ]);
                } else {
                    // Winner from B wins -> Bracket Reset! (Grand Final 2)
                    $resetMatch = $tournament->matches()
                        ->where('bracket_type', 'grand_final')
                        ->where('is_reset_match', true)
                        ->first();

                    if (!$resetMatch) {
                        $resetMatch = GameMatch::create([
                            'tournament_id' => $tournament->id,
                            'bracket_type' => 'grand_final',
                            'round' => 2,
                            'round_name' => 'Grand Finále – Rozhodující zápas (Reset)',
                            'match_number' => 2,
                            'is_reset_match' => true,
                            'player1_id' => $this->winner_id,
                            'player2_id' => $this->loser_id,
                            'status' => 'scheduled',
                        ]);
                    } else {
                        $resetMatch->update([
                            'player1_id' => $this->winner_id,
                            'player2_id' => $this->loser_id,
                            'status' => 'scheduled',
                            'winner_id' => null,
                            'loser_id' => null,
                            'player1_legs' => 0,
                            'player2_legs' => 0,
                        ]);
                    }
                }
            } else {
                // Grand Final 2 (Reset Match completed)
                $thirdPlace = $tournament->calculateThirdPlace();

                $tournament->update([
                    'winner_id' => $this->winner_id,
                    'second_place_id' => $this->loser_id,
                    'third_place_id' => $thirdPlace?->id,
                    'status' => 'finished',
                ]);
            }
        }
    }
}
