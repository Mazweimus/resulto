<?php

namespace App\Http\Controllers;

use App\Models\GameMatch;
use App\Models\Player;
use App\Models\Tournament;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    /**
     * Display the main darts tournament dashboard.
     */
    public function index()
    {
        $totalTournaments = Tournament::count();
        $activeTournaments = Tournament::where('status', 'ongoing')->count();
        $finishedTournamentsCount = Tournament::where('status', 'finished')->count();
        $totalPlayers = Player::count();

        // Poslední ukončené turnaje s pódiem (1., 2., 3. místo)
        $historyTournaments = Tournament::with(['winner', 'secondPlace', 'thirdPlace', 'players'])
            ->where('status', 'finished')
            ->orderByDesc('date')
            ->orderByDesc('id')
            ->take(6)
            ->get();

        // Aktuální běžící turnaje
        $ongoingTournaments = Tournament::with(['players'])
            ->withCount('matches')
            ->where('status', 'ongoing')
            ->orderByDesc('id')
            ->get();

        // Nadcházející připravené zápasy k odehrání
        $readyMatches = GameMatch::with(['tournament', 'player1', 'player2', 'winner'])
            ->where('status', 'scheduled')
            ->where('is_bye', false)
            ->whereNotNull('player1_id')
            ->whereNotNull('player2_id')
            ->orderBy('id')
            ->take(8)
            ->get();

        // Žebříček nejúspěšnějších hráčů podle vyhraných titulů a zápasů
        $topPlayers = Player::withCount(['wonTournaments', 'wonMatches'])
            ->orderByDesc('won_tournaments_count')
            ->orderByDesc('won_matches_count')
            ->take(6)
            ->get();

        return view('main', compact(
            'totalTournaments',
            'activeTournaments',
            'finishedTournamentsCount',
            'totalPlayers',
            'historyTournaments',
            'ongoingTournaments',
            'readyMatches',
            'topPlayers'
        ));
    }
}
