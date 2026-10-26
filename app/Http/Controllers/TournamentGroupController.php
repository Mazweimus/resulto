<?php

namespace App\Http\Controllers;

use App\Http\Requests\DrawGroupsRequest;
use App\Http\Requests\StoreGroupTournamentRequest;
use App\Models\Player;
use App\Models\Tournament;
use App\Services\TournamentGroupService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TournamentGroupController extends Controller
{
    public function __construct(
        protected TournamentGroupService $groupService
    ) {}

    /**
     * Show the wizard for creating a tournament with groups.
     */
    public function create(): View
    {
        $players = Player::orderBy('name')->get()->map(function ($player) {
            return [
                'id' => $player->id,
                'name' => $player->name,
                'gender' => $player->gender,
                'gender_label' => $player->gender_label,
                'matches_count' => $player->matches_count,
                'wins_count' => $player->wins_count,
            ];
        });

        return view('tournaments.groups.create', compact('players'));
    }

    /**
     * Randomly draw players into groups (AJAX / API endpoint).
     */
    public function draw(DrawGroupsRequest $request): JsonResponse
    {
        try {
            $playerIds = $request->input('player_ids', []);
            $groupsCount = (int) $request->input('groups_count', 2);

            $groups = $this->groupService->generateGroups($playerIds, $groupsCount, true);

            return response()->json([
                'success' => true,
                'message' => "Hráči byli úspěšně rozlosováni do {$groupsCount} skupin.",
                'groups_count' => $groupsCount,
                'total_players' => count($playerIds),
                'groups' => $groups,
            ]);
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Store tournament and its confirmed group configuration.
     */
    public function store(StoreGroupTournamentRequest $request): JsonResponse|RedirectResponse
    {
        try {
            $validated = $request->validated();

            $tournamentDate = $validated['date'] ?? now()->toDateString();
            $seasonYear = $request->filled('season_year')
                ? (int) $request->input('season_year')
                : (!empty($tournamentDate) ? (int) date('Y', strtotime($tournamentDate)) : (int) date('Y'));
            $countsForRanking = $request->has('counts_for_ranking')
                ? $request->boolean('counts_for_ranking')
                : true;

            $tournament = Tournament::create([
                'name' => $validated['name'],
                'date' => $tournamentDate,
                'status' => 'ongoing',
                'game_mode' => $validated['game_mode'] ?? '501',
                'checkout_mode' => $validated['checkout_mode'] ?? 'double_out',
                'format' => 'groups',
                'best_of_legs' => $validated['best_of_legs'] ?? 3,
                'counts_for_ranking' => $countsForRanking,
                'season_year' => $seasonYear,
            ]);

            // Save groups and generate round-robin matches
            $this->groupService->saveTournamentWithGroups($tournament, $validated['groups'], true);

            $message = "Turnaj \"{$tournament->name}\" byl úspěšně vytvořen a zápasy v základních skupinách byly rozlosovány!";

            if ($request->ajax() || $request->wantsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => $message,
                    'redirect_url' => route('tournaments.groups.show', $tournament->id),
                    'tournament_id' => $tournament->id,
                ]);
            }

            return redirect()->route('tournaments.groups.show', $tournament->id)
                ->with('success', $message);
        } catch (\InvalidArgumentException $e) {
            if ($request->ajax() || $request->wantsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => $e->getMessage(),
                ], 422);
            }

            return redirect()->back()
                ->withInput()
                ->with('error', $e->getMessage());
        }
    }

    /**
     * Display the tournament groups and their matches/tables.
     */
    public function show(Tournament $tournament): View
    {
        $tournament->load([
            'groups.players',
            'groups.matches.player1',
            'groups.matches.player2',
            'groups.matches.winner',
            'players',
        ]);

        $matchesGenerated = false;
        foreach ($tournament->groups as $group) {
            if ($group->matches->isEmpty() && $group->players->count() >= 2) {
                $group->generateRoundRobinMatches($tournament->best_of_legs ?? 3);
                $matchesGenerated = true;
            }
            // Vždy přepočítáme pořadí skupiny a tie-breakery pro aktuální zobrazení
            $group->recalculateStandings();
        }

        if ($matchesGenerated) {
            $tournament->load('groups.matches.player1', 'groups.matches.player2', 'groups.matches.winner');
        }

        return view('tournaments.groups.show', compact('tournament'));
    }
}
