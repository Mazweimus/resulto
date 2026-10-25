<?php

namespace App\Http\Controllers;

use App\Models\Player;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PlayerController extends Controller
{
    /**
     * Display a listing of players.
     */
    public function index(Request $request)
    {
        $search = $request->query('search');

        $query = Player::with(['wonTournaments', 'tournaments'])
            ->withCount(['tournaments', 'wonTournaments']);

        if (!empty($search)) {
            $query->where('name', 'like', "%{$search}%");
        }

        $players = $query->orderBy('name')->paginate(10)->withQueryString();

        $allPlayersCount = Player::count();

        return view('players.index', compact('players', 'search', 'allPlayersCount'));
    }

    /**
     * Show the form for creating a new player.
     */
    public function create()
    {
        return view('players.create');
    }

    /**
     * Store a newly created player in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100', 'unique:players,name'],
            'gender' => ['required', 'in:male,female'],
        ], [
            'name.required' => 'Zadejte prosím jméno hráče.',
            'name.unique' => 'Hráč s tímto jménem již existuje.',
            'gender.required' => 'Zvolte prosím pohlaví hráče.',
            'gender.in' => 'Neplatná hodnota pohlaví.',
        ]);

        $player = Player::create([
            'name' => $validated['name'],
            'gender' => $validated['gender'],
        ]);

        if ($request->ajax() || $request->wantsJson() || $request->expectsJson()) {
            return response()->json([
                'success' => true,
                'message' => "Hráč {$player->name} byl úspěšně zaevidován do databáze!",
                'player' => [
                    'id' => $player->id,
                    'name' => $player->name,
                    'gender' => $player->gender,
                    'gender_label' => $player->gender_label,
                    'matches_count' => 0,
                    'wins_count' => 0,
                    'win_rate' => 0,
                    'tournaments_count' => 0,
                ],
            ], 201);
        }

        return redirect()->route('players.index')
            ->with('success', "Hráč {$player->name} byl úspěšně zaevidován do databáze!");
    }

    /**
     * Show the form for editing the specified player.
     */
    public function edit(Player $player)
    {
        return view('players.edit', compact('player'));
    }

    /**
     * Update the specified player in storage.
     */
    public function update(Request $request, Player $player)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100', Rule::unique('players', 'name')->ignore($player->id)],
            'gender' => ['required', 'in:male,female'],
        ], [
            'name.required' => 'Zadejte prosím jméno hráče.',
            'name.unique' => 'Hráč s tímto jménem již existuje.',
            'gender.required' => 'Zvolte prosím pohlaví hráče.',
            'gender.in' => 'Neplatná hodnota pohlaví.',
        ]);

        $player->update([
            'name' => $validated['name'],
            'gender' => $validated['gender'],
        ]);

        if ($request->ajax() || $request->wantsJson() || $request->expectsJson()) {
            return response()->json([
                'success' => true,
                'message' => "Údaje hráče {$player->name} byly úspěšně aktualizovány.",
                'player' => [
                    'id' => $player->id,
                    'name' => $player->name,
                    'gender' => $player->gender,
                    'gender_label' => $player->gender_label,
                    'matches_count' => $player->matches_count,
                    'wins_count' => $player->wins_count,
                    'win_rate' => $player->win_rate,
                ],
            ]);
        }

        return redirect()->route('players.index')
            ->with('success', "Údaje hráče {$player->name} byly úspěšně aktualizovány.");
    }

    /**
     * Remove the specified player from storage.
     */
    public function destroy(Player $player)
    {
        $name = $player->name;
        $player->delete();

        return redirect()->route('players.index')
            ->with('success', "Hráč {$name} byl úspěšně odstraněn.");
    }
}
