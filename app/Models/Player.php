<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Player extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'gender',
    ];

    protected $appends = [
        'gender_label',
    ];

    public function tournaments(): BelongsToMany
    {
        return $this->belongsToMany(Tournament::class, 'tournament_players')
            ->withPivot('seed')
            ->withTimestamps();
    }

    public function tournamentStandings(): HasMany
    {
        return $this->hasMany(TournamentStanding::class);
    }

    public function getTotalPointsAttribute(): int
    {
        if (array_key_exists('total_points', $this->attributes)) {
            return (int) ($this->attributes['total_points'] ?? 0);
        }
        return (int) $this->tournamentStandings()
            ->whereHas('tournament', function ($q) {
                $q->where('counts_for_ranking', true);
            })
            ->sum('points');
    }

    public function getTournamentsPlayedCountAttribute(): int
    {
        if (array_key_exists('tournaments_count', $this->attributes)) {
            return (int) ($this->attributes['tournaments_count'] ?? 0);
        }
        return (int) $this->tournamentStandings()
            ->whereHas('tournament', function ($q) {
                $q->where('counts_for_ranking', true);
            })
            ->count();
    }

    public function wonTournaments(): HasMany
    {
        return $this->hasMany(Tournament::class, 'winner_id');
    }

    public function matchesAsPlayer1(): HasMany
    {
        return $this->hasMany(GameMatch::class, 'player1_id');
    }

    public function matchesAsPlayer2(): HasMany
    {
        return $this->hasMany(GameMatch::class, 'player2_id');
    }

    public function wonMatches(): HasMany
    {
        return $this->hasMany(GameMatch::class, 'winner_id');
    }

    public function getAllMatches()
    {
        return GameMatch::where(function ($q) {
            $q->where('player1_id', $this->id)
              ->orWhere('player2_id', $this->id);
        })->get();
    }

    public function getMatchesCountAttribute(): int
    {
        return GameMatch::where('status', 'completed')
            ->where(function ($q) {
                $q->where('player1_id', $this->id)
                  ->orWhere('player2_id', $this->id);
            })->count();
    }

    public function getWinsCountAttribute(): int
    {
        return $this->wonMatches()->count();
    }

    public function getWinRateAttribute(): float
    {
        $total = $this->matches_count;
        if ($total === 0) {
            return 0.0;
        }
        return round(($this->wins_count / $total) * 100, 1);
    }

    public function getDisplayNameAttribute(): string
    {
        return $this->name;
    }

    public function getGenderLabelAttribute(): string
    {
        return match ($this->gender) {
            'female' => 'Žena',
            default => 'Muž',
        };
    }
}
