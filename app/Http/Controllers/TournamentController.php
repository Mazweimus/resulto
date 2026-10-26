<?php

namespace App\Http\Controllers;

use App\Models\GameMatch;
use App\Models\Player;
use App\Models\Tournament;
use Illuminate\Http\Request;

class TournamentController extends Controller
{
    /**
     * Display a listing of tournaments.
     */
    public function index()
    {
        $tournaments = Tournament::with(['winner', 'secondPlace', 'thirdPlace', 'players'])
            ->withCount('matches')
            ->orderByDesc('id')
            ->paginate(10);

        return view('tournaments.index', compact('tournaments'));
    }

    /**
     * Show the form for creating a new tournament.
     */
    public function create()
    {
        $players = Player::orderBy('name')->get()->map(function ($player) {
            return [
                'id' => $player->id,
                'name' => $player->name,
                'gender' => $player->gender,
                'gender_label' => $player->gender_label,
                'matches_count' => $player->matches_count,
                'wins_count' => $player->wins_count,
                'win_rate' => $player->win_rate,
            ];
        });

        return view('tournaments.create', compact('players'));
    }

    /**
     * Store a newly created tournament in storage.
     */
    public function store(Request $request)
    {
        $format = $request->input('format', $request->input('type', 'double_elimination'));

        // FREE formáty: Vytvoření prázdného pavouka o zvolené velikosti (8, 16, 32)
        if (in_array($format, ['free_oneway_elimination', 'free_double_elimination'])) {
            $bracketSize = (int) ($request->input('bracket_size') ?? $request->input('size') ?? 8);
            if (!in_array($bracketSize, [8, 16, 32])) {
                $bracketSize = 8;
            }

            $validated = $request->validate([
                'name' => ['required', 'string', 'max:150'],
                'date' => ['nullable', 'date'],
                'bracket_size' => ['nullable', 'in:8,16,32'],
                'size' => ['nullable', 'in:8,16,32'],
                'counts_for_ranking' => ['nullable', 'boolean'],
                'season_year' => ['nullable', 'integer', 'min:2020', 'max:2100'],
            ], [
                'name.required' => 'Zadejte prosím název turnaje.',
                'bracket_size.in' => 'Velikost pavouka musí být 8, 16 nebo 32 pozic.',
                'size.in' => 'Velikost pavouka musí být 8, 16 nebo 32 pozic.',
                'season_year.min' => 'Ročník turnaje musí být od roku 2020.',
                'season_year.max' => 'Ročník turnaje může být maximálně do roku 2100.',
            ]);

            $tournamentDate = $validated['date'] ?? now()->toDateString();
            $seasonYear = $request->filled('season_year')
                ? (int) $request->input('season_year')
                : (!empty($tournamentDate) ? (int) date('Y', strtotime($tournamentDate)) : (int) date('Y'));
            $countsForRanking = $request->has('counts_for_ranking') ? $request->boolean('counts_for_ranking') : true;

            $tournament = Tournament::create([
                'name' => $validated['name'],
                'date' => $tournamentDate,
                'status' => 'in_progress',
                'game_mode' => '501',
                'checkout_mode' => 'double_out',
                'format' => $format,
                'bracket_size' => $bracketSize,
                'counts_for_ranking' => $countsForRanking,
                'season_year' => $seasonYear,
                'is_seeding_finalized' => false,
                'best_of_legs' => $request->input('best_of_legs', 3),
            ]);

            $service = new \App\Services\TournamentService();
            if ($format === 'free_double_elimination') {
                $service->generateFreeDoubleBracket($tournament, $bracketSize);
            } else {
                $service->generateFreeOnewayBracket($tournament, $bracketSize);
            }

            // Pokud byly předány sloty/hráči (např. v testu nebo z formuláře)
            $rawSlots = $request->input('slots', $request->input('player_ids', []));
            if (!empty($rawSlots)) {
                $r1Matches = $tournament->matches()
                    ->where('bracket_type', 'winners')
                    ->where('round', 1)
                    ->orderBy('match_number')
                    ->get();
                foreach ($rawSlots as $idx => $val) {
                    if (!empty($val)) {
                        $matchIdx = (int) floor($idx / 2);
                        $slotNum = ($idx % 2) + 1;
                        $playerId = is_numeric($val) ? (int)$val : Player::firstOrCreate(['name' => trim($val)], ['gender' => 'male'])->id;
                        if (isset($r1Matches[$matchIdx])) {
                            $m = $r1Matches[$matchIdx];
                            if ($slotNum === 1) $m->player1_id = $playerId;
                            else $m->player2_id = $playerId;
                            $m->save();
                        }
                    }
                }

                // Pokud bylo předáno dost hráčů a požadavek obsahuje flag finalize nebo jsou splněny podmínky
                if ($request->boolean('finalize')) {
                    $service->finalizeFreeSeeding($tournament);
                }
            }

            return redirect()->route('tournaments.play', $tournament->id)
                ->with('success', "Turnaj \"{$tournament->name}\" byl úspěšně vytvořen. Nyní můžete v pavouku interaktivně nasadit hráče do jednotlivých slotů.");
        }

        // Skupinový formát: Inline vytváření skupin
        if ($format === 'groups') {
            $groupsData = $request->input('groups', []);
            $playerIds = $request->input('player_ids', $request->input('players', []));
            $groupsCount = (int) ($request->input('groups_count') ?? 2);

            $validated = $request->validate([
                'name' => ['required', 'string', 'max:150'],
                'date' => ['nullable', 'date'],
                'counts_for_ranking' => ['nullable', 'boolean'],
                'season_year' => ['nullable', 'integer', 'min:2020', 'max:2100'],
                'best_of_legs' => ['nullable', 'integer', 'min:1', 'max:35'],
                'groups' => ['nullable', 'array'],
                'player_ids' => ['nullable', 'array'],
                'players' => ['nullable', 'array'],
            ], [
                'name.required' => 'Zadejte prosím název turnaje.',
                'season_year.min' => 'Ročník turnaje musí být od roku 2020.',
                'season_year.max' => 'Ročník turnaje může být maximálně do roku 2100.',
            ]);

            $tournamentDate = $validated['date'] ?? now()->toDateString();
            $seasonYear = $request->filled('season_year')
                ? (int) $request->input('season_year')
                : (!empty($tournamentDate) ? (int) date('Y', strtotime($tournamentDate)) : (int) date('Y'));
            $countsForRanking = $request->has('counts_for_ranking') ? $request->boolean('counts_for_ranking') : true;

            try {
                $groupService = app(\App\Services\TournamentGroupService::class);

                // Pokud groups nebyly odeslány, ale máme player_ids, vygenerujeme je automaticky
                if (empty($groupsData) && !empty($playerIds)) {
                    $groupsData = $groupService->generateGroups($playerIds, $groupsCount);
                }

                if (empty($groupsData)) {
                    return back()->withInput()->with('error', 'Při zakládání turnaje se skupinami musíte vybrat hráče a rozlosovat je do skupin.');
                }

                // Validace konfigurace skupin
                $groupService->validateGroupConfiguration($groupsData);

                $tournament = Tournament::create([
                    'name' => $validated['name'],
                    'date' => $tournamentDate,
                    'status' => 'ongoing',
                    'game_mode' => '501',
                    'checkout_mode' => 'double_out',
                    'format' => 'groups',
                    'counts_for_ranking' => $countsForRanking,
                    'season_year' => $seasonYear,
                    'best_of_legs' => $validated['best_of_legs'] ?? 3,
                ]);

                $groupService->saveTournamentWithGroups($tournament, $groupsData, true);

                $message = "Turnaj \"{$tournament->name}\" byl úspěšně vytvořen a zápasy ve skupinách byly rozlosovány!";

                if ($request->ajax() || $request->wantsJson()) {
                    return response()->json([
                        'success' => true,
                        'message' => $message,
                        'redirect_url' => route('tournaments.show', $tournament->id),
                        'tournament_id' => $tournament->id,
                    ]);
                }

                return redirect()->route('tournaments.show', $tournament->id)
                    ->with('success', $message);
            } catch (\InvalidArgumentException $e) {
                if ($request->ajax() || $request->wantsJson()) {
                    return response()->json([
                        'success' => false,
                        'message' => $e->getMessage(),
                    ], 422);
                }
                return back()->withInput()->with('error', $e->getMessage());
            }
        }

        // Standardní formáty (Double Elimination): Vyžaduje výběr hráčů
        if (!$request->has('player_ids') && $request->has('players')) {
            $request->merge(['player_ids' => $request->input('players')]);
        }
        if (!$request->has('players') && $request->has('player_ids')) {
            $request->merge(['players' => $request->input('player_ids')]);
        }

        // Výchozí hodnoty pro pole odebraná z formuláře UI
        if (!$request->filled('date')) {
            $request->merge(['date' => now()->toDateString()]);
        }
        if (!$request->filled('best_of_legs')) {
            $request->merge(['best_of_legs' => 3]);
        }
        if (!$request->filled('game_mode')) {
            $request->merge(['game_mode' => '501']);
        }
        if (!$request->filled('checkout_mode')) {
            $request->merge(['checkout_mode' => 'double_out']);
        }
        if (!$request->filled('format')) {
            $request->merge(['format' => 'double_elimination']);
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'date' => ['nullable', 'date'],
            'game_mode' => ['nullable', 'string'],
            'checkout_mode' => ['nullable', 'string'],
            'format' => ['nullable', 'string'],
            'best_of_legs' => ['nullable', 'integer', 'min:1', 'max:35'],
            'counts_for_ranking' => ['nullable', 'boolean'],
            'season_year' => ['nullable', 'integer', 'min:2020', 'max:2100'],
            'player_ids' => ['required', 'array', 'min:2'],
            'player_ids.*' => ['exists:players,id'],
        ], [
            'name.required' => 'Zadejte prosím název turnaje.',
            'date.required' => 'Zvolte datum konání turnaje.',
            'player_ids.required' => 'Vyberte alespoň 2 hráče pro turnaj.',
            'player_ids.min' => 'Pro turnaj je potřeba vybrat minimálně 2 hráče.',
            'season_year.min' => 'Ročník turnaje musí být od roku 2020.',
            'season_year.max' => 'Ročník turnaje může být maximálně do roku 2100.',
        ]);

        $tournamentDate = $validated['date'] ?? now()->toDateString();
        $seasonYear = $request->filled('season_year')
            ? (int) $request->input('season_year')
            : (!empty($tournamentDate) ? (int) date('Y', strtotime($tournamentDate)) : (int) date('Y'));
        $countsForRanking = $request->has('counts_for_ranking') ? $request->boolean('counts_for_ranking') : true;

        $tournament = Tournament::create([
            'name' => $validated['name'],
            'date' => $tournamentDate,
            'status' => 'ongoing',
            'game_mode' => $validated['game_mode'] ?? '501',
            'checkout_mode' => $validated['checkout_mode'] ?? 'double_out',
            'format' => $validated['format'] ?? 'double_elimination',
            'counts_for_ranking' => $countsForRanking,
            'season_year' => $seasonYear,
            'best_of_legs' => $validated['best_of_legs'] ?? 3,
        ]);

        // Náhodné rozlosování hráčů pro Double Elimination
        $shuffledPlayerIds = collect($validated['player_ids'])->shuffle()->values()->all();

        // Attach selected players
        $tournament->players()->attach($shuffledPlayerIds);

        // Generate Double Elimination bracket
        $tournament->generateDoubleEliminationMatches($shuffledPlayerIds);

        return redirect()->route('tournaments.play', $tournament->id)
            ->with('success', "Turnaj \"{$tournament->name}\" byl úspěšně vytvořen a pavouci Double Elimination (A a B) byli rozlosováni!");
    }

    /**
     * Assign or clear a player in a Round 1 match slot of FREE Elimination.
     */
    public function assignSlot(Request $request, Tournament $tournament)
    {
        $validated = $request->validate([
            'match_id' => ['required', 'integer'],
            'slot' => ['required', 'in:1,2'],
            'player_id' => ['nullable'],
        ]);

        $playerId = !empty($validated['player_id']) ? $validated['player_id'] : null;
        if (is_numeric($playerId)) {
            $playerId = (int) $playerId;
        }

        try {
            $service = new \App\Services\TournamentService();
            $status = $service->assignSlot(
                $tournament,
                (int) $validated['match_id'],
                (int) $validated['slot'],
                $playerId
            );

            $match = GameMatch::with(['player1', 'player2'])->find($validated['match_id']);

            return response()->json([
                'success' => true,
                'message' => 'Slot byl úspěšně aktualizován.',
                'match' => [
                    'id' => $match->id,
                    'slot' => (int) $validated['slot'],
                    'player1' => $match->player1 ? ['id' => $match->player1->id, 'name' => $match->player1->name] : null,
                    'player2' => $match->player2 ? ['id' => $match->player2->id, 'name' => $match->player2->name] : null,
                ],
                'seeding_status' => $status,
            ]);
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Finalize seeding for FREE Elimination, evaluate BYEs, and start the tournament.
     */
    public function finalizeSeeding(Request $request, Tournament $tournament)
    {
        try {
            $service = new \App\Services\TournamentService();
            $result = $service->finalizeFreeSeeding($tournament);

            if ($request->ajax() || $request->wantsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => $result['message'],
                    'redirect_url' => route('tournaments.play', $tournament->id),
                ]);
            }

            return redirect()->route('tournaments.play', $tournament->id)
                ->with('success', $result['message']);
        } catch (\InvalidArgumentException $e) {
            if ($request->ajax() || $request->wantsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => $e->getMessage(),
                ], 422);
            }

            return redirect()->route('tournaments.play', $tournament->id)
                ->with('error', $e->getMessage());
        }
    }

    /**
     * Display the specified tournament.
     */
    public function show(Tournament $tournament)
    {
        if ($tournament->format === 'groups') {
            return redirect()->route('tournaments.groups.show', $tournament->id);
        }
        return $this->renderTournamentView($tournament, 'tournaments.show');
    }

    /**
     * Dedicated page for playing tournament and recording scores in Double Elimination.
     */
    public function play(Tournament $tournament)
    {
        if ($tournament->format === 'groups') {
            return redirect()->route('tournaments.groups.show', $tournament->id);
        }
        return $this->renderTournamentView($tournament, 'tournaments.play');
    }

    protected function renderTournamentView(Tournament $tournament, string $viewName)
    {
        $tournament->load([
            'matches.player1',
            'matches.player2',
            'matches.winner',
            'matches.loser',
            'players',
            'winner',
            'secondPlace',
            'thirdPlace',
            'standings.player',
        ]);

        $winnersMatches = $tournament->winnersMatches->groupBy('round');
        $losersMatches = $tournament->losersMatches->groupBy('round');
        $grandFinalMatches = $tournament->grandFinalMatches;

        $thirdPlaceMatch = $tournament->thirdPlaceMatch;
        $thirdPlace = $tournament->thirdPlace ?? $tournament->calculateThirdPlace();
        $standings = (new \App\Services\TournamentService())->calculateStandings($tournament);

        $allPlayers = Player::orderBy('name')->get();
        $isFreeFormat = in_array($tournament->format, ['free_oneway_elimination', 'free_double_elimination']);
        $seedingStatus = $isFreeFormat
            ? (new \App\Services\TournamentService())->getSeedingStatus($tournament)
            : null;

        if (in_array($tournament->status, ['finished', 'completed']) && $tournament->standings()->count() === 0) {
            (new \App\Services\TournamentService())->calculateAndSaveStandings($tournament);
        }

        return view($viewName, compact(
            'tournament',
            'winnersMatches',
            'losersMatches',
            'grandFinalMatches',
            'thirdPlaceMatch',
            'thirdPlace',
            'standings',
            'allPlayers',
            'seedingStatus'
        ));
    }

    /**
     * Show the form for editing the specified tournament.
     */
    public function edit(Tournament $tournament)
    {
        return view('tournaments.edit', compact('tournament'));
    }

    /**
     * Update the specified tournament in storage.
     */
    public function update(Request $request, Tournament $tournament)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'date' => ['nullable', 'date'],
            'best_of_legs' => ['nullable', 'integer', 'min:1', 'max:35'],
            'counts_for_ranking' => ['nullable', 'boolean'],
            'season_year' => ['nullable', 'integer', 'min:2020', 'max:2100'],
        ], [
            'name.required' => 'Zadejte prosím název turnaje.',
            'season_year.min' => 'Ročník turnaje musí být od roku 2020.',
            'season_year.max' => 'Ročník turnaje může být maximálně do roku 2100.',
        ]);

        $countsForRanking = $request->has('counts_for_ranking')
            ? $request->boolean('counts_for_ranking')
            : $tournament->counts_for_ranking;

        $tournamentDate = $validated['date'] ?? ($tournament->date ? $tournament->date->toDateString() : now()->toDateString());
        $seasonYear = $request->filled('season_year')
            ? (int) $request->input('season_year')
            : ($tournament->season_year ?? (!empty($tournamentDate) ? (int) date('Y', strtotime($tournamentDate)) : (int) date('Y')));

        $tournament->update([
            'name' => $validated['name'],
            'date' => $tournamentDate,
            'best_of_legs' => $validated['best_of_legs'] ?? $tournament->best_of_legs,
            'counts_for_ranking' => $countsForRanking,
            'season_year' => $seasonYear,
        ]);

        return redirect()->route('tournaments.show', $tournament->id)
            ->with('success', "Turnaj \"{$tournament->name}\" byl úspěšně upraven.");
    }

    /**
     * Remove the specified tournament from storage.
     */
    public function destroy(Tournament $tournament)
    {
        $name = $tournament->name;
        $tournament->delete();

        return redirect()->route('tournaments.index')
            ->with('success', "Turnaj \"{$name}\" byl úspěšně smazán.");
    }
}
