<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TournamentStanding extends Model
{
    use HasFactory;

    protected $fillable = [
        'tournament_id',
        'player_id',
        'rank_from',
        'rank_to',
        'points',
    ];

    protected $casts = [
        'tournament_id' => 'integer',
        'player_id' => 'integer',
        'rank_from' => 'integer',
        'rank_to' => 'integer',
        'points' => 'integer',
    ];

    public function tournament(): BelongsTo
    {
        return $this->belongsTo(Tournament::class);
    }

    public function player(): BelongsTo
    {
        return $this->belongsTo(Player::class);
    }

    /**
     * Text representation of rank, e.g. "1.", "3.", "5.–6.".
     */
    public function getRankLabelAttribute(): string
    {
        if ($this->rank_from === $this->rank_to) {
            return "{$this->rank_from}.";
        }

        return "{$this->rank_from}.–{$this->rank_to}.";
    }

    /**
     * Calculate points according to official Resulto scale:
     * - 1. place: 10 points
     * - 2. place: 9 points
     * - 3. place: 8 points
     * - 4. place: 7 points
     * - 5.–6. place: 6 points (losers in quarterfinal / corresponding LB round)
     * - 7.–8. place: 5 points
     * - 9.–12. place: 4 points
     * - 13.–16. place: 3 points
     * - 17.–24. place: 2 points
     * - 25.–32. place: 1 point
     *
     * @param int $rankFrom
     * @param int $rankTo
     * @return int
     */
    public static function calculatePoints(int $rankFrom, int $rankTo): int
    {
        if ($rankFrom === 1) return 10;
        if ($rankFrom === 2) return 9;
        if ($rankFrom === 3) {
            return ($rankTo === 3) ? 8 : 7;
        }
        if ($rankFrom === 4) return 7;
        if ($rankFrom === 5 || $rankFrom === 6) return 6; // 5.–6. (or 5.–8. in Single Elimination QFs)
        if ($rankFrom >= 7 && $rankFrom <= 8) return 5;   // 7.–8.
        if ($rankFrom >= 9 && $rankFrom <= 12) return 4;  // 9.–12. (or 9.–16.)
        if ($rankFrom >= 13 && $rankFrom <= 16) return 3; // 13.–16.
        if ($rankFrom >= 17 && $rankFrom <= 24) return 2; // 17.–24.
        if ($rankFrom >= 25 && $rankFrom <= 32) return 1; // 25.–32.
        return 1;
    }
}
