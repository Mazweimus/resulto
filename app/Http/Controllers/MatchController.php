<?php

namespace App\Http\Controllers;

use App\Models\GameMatch;
use Illuminate\Http\Request;

class MatchController extends Controller
{
    /**
     * Update match score (legs only) and advance winner/loser in the tournament.
     */
    public function update(Request $request, GameMatch $match)
    {
        $validated = $request->validate([
            'player1_legs' => ['required', 'integer', 'min:0'],
            'player2_legs' => ['required', 'integer', 'min:0'],
            'status' => ['nullable', 'in:scheduled,completed'],
        ], [
            'player1_legs.required' => 'Zadejte vyhrané legy pro prvního hráče.',
            'player2_legs.required' => 'Zadejte vyhrané legy pro druhého hráče.',
        ]);

        $p1Legs = (int) $validated['player1_legs'];
        $p2Legs = (int) $validated['player2_legs'];

        $oldWinnerId = $match->winner_id;
        $oldLoserId = $match->loser_id;
        $oldStatus = $match->status;

        if ($p1Legs === 0 && $p2Legs === 0 && ($validated['status'] ?? '') !== 'completed') {
            $match->update([
                'player1_legs' => 0,
                'player2_legs' => 0,
                'winner_id' => null,
                'loser_id' => null,
                'status' => 'scheduled',
            ]);

            // Kaskádovité pročištění navazujících zápasů
            $match->cascadeClearDownstream($oldWinnerId, $oldLoserId);

            $match->tournament->recalculateStatus();

            return back()->with('success', "Zápas {$match->round_name} byl resetován a navazující zápasy byly přepočítány.");
        }

        if ($p1Legs === $p2Legs && ($p1Legs > 0 || $p2Legs > 0)) {
            return back()->with('error', 'Vyřazovací zápas nemůže skončit remízou. Jeden z hráčů musí zvítězit.');
        }

        $winnerId = null;
        $loserId = null;
        $status = 'completed';

        if ($p1Legs > $p2Legs) {
            $winnerId = $match->player1_id;
            $loserId = $match->player2_id;
        } elseif ($p2Legs > $p1Legs) {
            $winnerId = $match->player2_id;
            $loserId = $match->player1_id;
        } else {
            // Remíza
            $status = $validated['status'] ?? 'scheduled';
        }

        $match->update([
            'player1_legs' => $p1Legs,
            'player2_legs' => $p2Legs,
            'winner_id' => $winnerId,
            'loser_id' => $loserId,
            'status' => $status,
        ]);

        if ($status === 'completed' && $winnerId) {
            // Pokud došlo ke změně vítěze/poraženého u již odehraného zápasu, pročistíme starou větev
            if ($oldStatus === 'completed' && ($oldWinnerId !== $winnerId || $oldLoserId !== $loserId)) {
                $match->cascadeClearDownstream($oldWinnerId, $oldLoserId);
            }

            $match->advanceParticipants();
        }

        $match->tournament->recalculateStatus();

        $winnerName = $match->winner ? $match->winner->name : 'Nerozhodně';
        return back()->with('success', "Výsledek zápasu {$match->round_name} byl zapsán! Skóre: {$p1Legs}:{$p2Legs} (Vítěz: {$winnerName})");
    }
}
