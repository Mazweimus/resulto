<?php

namespace App\Http\Controllers;

use App\Models\Player;
use App\Models\Tournament;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class LeaderboardController extends Controller
{
    /**
     * Display the player leaderboard filtered by season year or All-Time.
     */
    public function index(Request $request)
    {
        $currentYear = (int) date('Y');

        // Dynamicky načtené ročníky ze započítávaných dokončených turnajů v databázi
        $seasonYearsFromDb = Tournament::query()
            ->where('counts_for_ranking', true)
            ->whereIn('status', ['completed', 'finished'])
            ->whereNotNull('season_year')
            ->distinct()
            ->orderByDesc('season_year')
            ->pluck('season_year')
            ->map(fn($y) => (int) $y)
            ->toArray();

        // Seznam dostupných ročníků, vždy včetně aktuálního roku
        $availableYears = array_values(array_unique(array_merge([$currentYear], $seasonYearsFromDb)));
        rsort($availableYears);

        // Parametr ?season=... (např. '2026', '2025' nebo 'all_time'), defaultně aktuální rok
        $seasonParam = $request->query('season', (string) $currentYear);
        $isAllTime = ($seasonParam === 'all_time');
        $selectedYear = is_numeric($seasonParam) ? (int) $seasonParam : $currentYear;

        // Celkový počet dokončených turnajů pro dané období
        $tournamentsCount = Tournament::query()
            ->where('counts_for_ranking', true)
            ->whereIn('status', ['completed', 'finished'])
            ->when(!$isAllTime, fn($q) => $q->where('season_year', $selectedYear))
            ->count();

        // Základní dotaz pro žebříček:
        // JOIN tournament_standings a tournaments se striktním GROUP BY podle ID hráče
        $query = Player::query()
            ->join('tournament_standings', 'players.id', '=', 'tournament_standings.player_id')
            ->join('tournaments', 'tournament_standings.tournament_id', '=', 'tournaments.id')
            ->where('tournaments.counts_for_ranking', true)
            ->whereIn('tournaments.status', ['completed', 'finished']);

        if (!$isAllTime) {
            $query->where('tournaments.season_year', $selectedYear);
        }

        $rankedPlayers = $query->select(
                'players.id',
                'players.name',
                'players.gender',
                DB::raw('SUM(tournament_standings.points) as total_points'),
                DB::raw('COUNT(DISTINCT tournaments.id) as tournaments_played')
            )
            ->groupBy('players.id', 'players.name', 'players.gender')
            ->orderByDesc('total_points')
            ->orderByDesc('tournaments_played')
            ->orderBy('players.name')
            ->get();

        // Typová konverze a nastavení kompatibilních atributů
        $rankedPlayers->transform(function ($player) {
            $player->total_points = (int) $player->total_points;
            $player->tournaments_played = (int) $player->tournaments_played;
            $player->tournaments_count = $player->tournaments_played;
            return $player;
        });

        // Pojistka proti duplicitním záznamům hráčů se stejným jménem v DB
        $deduplicated = collect();
        $groupedByName = $rankedPlayers->groupBy(fn($p) => mb_strtolower(trim($p->name)));

        foreach ($groupedByName as $group) {
            if ($group->count() === 1) {
                $deduplicated->push($group->first());
            } else {
                $first = $group->first();
                $merged = clone $first;
                $merged->total_points = (int) $group->sum('total_points');
                $merged->tournaments_played = (int) $group->sum('tournaments_played');
                $merged->tournaments_count = $merged->tournaments_played;
                $deduplicated->push($merged);
            }
        }

        $leaderboard = $deduplicated->sortByDesc('total_points')
            ->sortByDesc('tournaments_played')
            ->values();

        // Pokud je ročník bez turnajů nebo bez bodů, předáváme prázdnou kolekci pro prázdný stav
        $displayPlayers = ($tournamentsCount > 0 && $leaderboard->isNotEmpty()) ? $leaderboard : collect();

        return view('leaderboard.index', [
            'players' => $displayPlayers,
            'leaderboard' => $displayPlayers,
            'seasonParam' => $seasonParam,
            'isAllTime' => $isAllTime,
            'selectedYear' => $selectedYear,
            'currentYear' => $currentYear,
            'availableYears' => $availableYears,
            'tournamentsCount' => $tournamentsCount,
        ]);
    }
}
