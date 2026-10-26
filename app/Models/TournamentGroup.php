<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

class TournamentGroup extends Model
{
    use HasFactory;

    protected $table = 'tournament_groups';

    protected $fillable = [
        'tournament_id',
        'name',
        'order',
        'stage',
    ];

    protected $casts = [
        'order' => 'integer',
    ];

    public function tournament(): BelongsTo
    {
        return $this->belongsTo(Tournament::class);
    }

    public function players(): BelongsToMany
    {
        return $this->belongsToMany(Player::class, 'tournament_group_players')
            ->withPivot([
                'seed',
                'points',
                'matches_played',
                'matches_won',
                'matches_drawn',
                'matches_lost',
                'legs_won',
                'legs_lost',
            ])
            ->withTimestamps()
            ->orderByPivot('seed', 'asc');
    }

    public function matches(): HasMany
    {
        return $this->hasMany(GameMatch::class, 'tournament_group_id')
            ->orderBy('round')
            ->orderBy('match_number');
    }

    /**
     * Get the single letter of the group (e.g. "A", "B", "AA").
     */
    public function getLetterAttribute(): string
    {
        return trim(str_ireplace('Skupina', '', $this->name));
    }

    /**
     * Generate round robin matches for all players in this group.
     */
    public function generateRoundRobinMatches(int $bestOfLegs = 3): Collection
    {
        $players = $this->players()->get();
        $playerCount = $players->count();
        $matches = collect();

        if ($playerCount < 2) {
            return $matches;
        }

        $matchNum = 1;
        $round = 1;

        // Round robin pairing: each pair plays once
        for ($i = 0; $i < $playerCount; $i++) {
            for ($j = $i + 1; $j < $playerCount; $j++) {
                $p1 = $players[$i];
                $p2 = $players[$j];

                $match = GameMatch::create([
                    'tournament_id' => $this->tournament_id,
                    'tournament_group_id' => $this->id,
                    'bracket_type' => 'group',
                    'round' => $round,
                    'round_name' => "{$this->name} - Kolo {$round}",
                    'match_number' => $matchNum++,
                    'player1_id' => $p1->id,
                    'player2_id' => $p2->id,
                    'status' => 'pending',
                ]);

                $matches->push($match);
                $round++;
            }
        }

        return $matches;
    }

    /**
     * Recalculate group table statistics and sort players by tie-breakers.
     *
     * @return Collection
     */
    public function recalculateStandings(): Collection
    {
        return (new \App\Actions\RecalculateGroupStandingsAction())->execute($this);
    }

    /**
     * Get ranked group standings collection.
     *
     * @return Collection
     */
    public function getStandings(): Collection
    {
        return (new \App\Actions\RecalculateGroupStandingsAction())->execute($this);
    }
}
