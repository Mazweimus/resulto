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
            'player1_legs' => ['nullable', 'integer', 'min:0'],
            'player2_legs' => ['nullable', 'integer', 'min:0'],
            'status' => ['nullable', 'string', 'in:pending,in_progress,completed,scheduled'],
        ], [
            'player1_legs.integer' => 'Vyhrané legy musí být celé číslo.',
            'player2_legs.integer' => 'Vyhrané legy musí být celé číslo.',
        ]);

        $oldWinnerId = $match->winner_id;
        $oldLoserId = $match->loser_id;
        $oldStatus = $match->status;

        // Případ 1: Změna pouze stavu zápasu (bez zaslání skóre - např. z dropdownu v kartě zápasu)
        if (!$request->has('player1_legs') && !$request->has('player2_legs') && $request->filled('status')) {
            $newStatus = $validated['status'];
            if ($newStatus === 'scheduled') {
                $newStatus = 'pending';
            }

            $updateData = ['status' => $newStatus];

            if (in_array($newStatus, ['pending', 'in_progress'])) {
                // Pokud byl zápas dokončen, zrušíme vítěze/poraženého, protože zápas již není dokončen
                if ($oldStatus === 'completed') {
                    $updateData['winner_id'] = null;
                    $updateData['loser_id'] = null;
                    $match->cascadeClearDownstream($oldWinnerId, $oldLoserId);
                }
            } elseif ($newStatus === 'completed') {
                // Pokud přechází do completed a má skóre, spočítáme vítěze/poraženého
                if ($match->player1_legs > $match->player2_legs) {
                    $updateData['winner_id'] = $match->player1_id;
                    $updateData['loser_id'] = $match->player2_id;
                } elseif ($match->player2_legs > $match->player1_legs) {
                    $updateData['winner_id'] = $match->player2_id;
                    $updateData['loser_id'] = $match->player1_id;
                } else {
                    $updateData['winner_id'] = null;
                    $updateData['loser_id'] = null;
                }
            }

            $match->update($updateData);

            $standings = null;
            if ($match->tournament_group_id) {
                $standings = $match->tournamentGroup?->recalculateStandings();
            }

            $match->tournament->recalculateStatus();

            if ($request->wantsJson() || $request->ajax() || $request->isJson()) {
                return response()->json([
                    'success' => true,
                    'message' => "Stav zápasu byl změněn na \"{$match->status_label}\".",
                    'match' => [
                        'id' => $match->id,
                        'tournament_group_id' => $match->tournament_group_id,
                        'player1_id' => $match->player1_id,
                        'player2_id' => $match->player2_id,
                        'player1_legs' => $match->player1_legs,
                        'player2_legs' => $match->player2_legs,
                        'winner_id' => $match->winner_id,
                        'loser_id' => $match->loser_id,
                        'status' => $match->status,
                        'status_label' => $match->status_label,
                        'status_badge_class' => $match->status_badge_class,
                    ],
                    'standings' => $standings,
                ]);
            }

            return back()->with('success', "Stav zápasu byl změněn na \"{$match->status_label}\".");
        }

        // Případ 2: Běžné zadání skóre (nebo reset)
        $p1Legs = isset($validated['player1_legs']) ? (int) $validated['player1_legs'] : 0;
        $p2Legs = isset($validated['player2_legs']) ? (int) $validated['player2_legs'] : 0;

        // Reset zápasu na 0:0
        if ($p1Legs === 0 && $p2Legs === 0 && ($validated['status'] ?? '') !== 'completed') {
            $match->update([
                'player1_legs' => 0,
                'player2_legs' => 0,
                'winner_id' => null,
                'loser_id' => null,
                'status' => 'pending',
            ]);

            // Kaskádovité pročištění navazujících zápasů
            $match->cascadeClearDownstream($oldWinnerId, $oldLoserId);

            $standings = null;
            if ($match->tournament_group_id) {
                $standings = $match->tournamentGroup?->recalculateStandings();
            }

            $match->tournament->recalculateStatus();

            if ($request->wantsJson() || $request->ajax() || $request->isJson()) {
                return response()->json([
                    'success' => true,
                    'message' => "Zápas {$match->round_name} byl resetován.",
                    'match' => [
                        'id' => $match->id,
                        'tournament_group_id' => $match->tournament_group_id,
                        'player1_id' => $match->player1_id,
                        'player2_id' => $match->player2_id,
                        'player1_legs' => 0,
                        'player2_legs' => 0,
                        'winner_id' => null,
                        'loser_id' => null,
                        'status' => 'pending',
                        'status_label' => $match->status_label,
                        'status_badge_class' => $match->status_badge_class,
                    ],
                    'standings' => $standings,
                ]);
            }

            return back()->with('success', "Zápas {$match->round_name} byl resetován a navazující zápasy byly přepočítány.");
        }

        $isGroupMatch = !empty($match->tournament_group_id) || $match->bracket_type === 'group';

        if (!$isGroupMatch && $p1Legs === $p2Legs && ($p1Legs > 0 || $p2Legs > 0)) {
            if ($request->wantsJson() || $request->ajax() || $request->isJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Vyřazovací zápas nemůže skončit remízou. Jeden z hráčů musí zvítězit.',
                ], 422);
            }
            return back()->with('error', 'Vyřazovací zápas nemůže skončit remízou. Jeden z hráčů musí zvítězit.');
        }

        $winnerId = null;
        $loserId = null;
        // Automatické nastavení na 'completed' při zapsání platného výsledku
        $status = 'completed';

        if ($p1Legs > $p2Legs) {
            $winnerId = $match->player1_id;
            $loserId = $match->player2_id;
        } elseif ($p2Legs > $p1Legs) {
            $winnerId = $match->player2_id;
            $loserId = $match->player1_id;
        } else {
            // Remíza v základní skupině
            $winnerId = null;
            $loserId = null;
            $status = ($p1Legs > 0 || $p2Legs > 0 || ($validated['status'] ?? '') === 'completed') ? 'completed' : 'pending';
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

        // Pokud zápas patří do základní skupiny, přepočítáme pořadí a tabulku skupiny
        $standings = null;
        if ($match->tournament_group_id) {
            $standings = $match->tournamentGroup?->recalculateStandings();
        }

        $match->tournament->recalculateStatus();

        if ($request->wantsJson() || $request->ajax() || $request->isJson()) {
            return response()->json([
                'success' => true,
                'message' => "Výsledek byl úspěšně uložen.",
                'match' => [
                    'id' => $match->id,
                    'tournament_group_id' => $match->tournament_group_id,
                    'player1_id' => $match->player1_id,
                    'player2_id' => $match->player2_id,
                    'player1_legs' => $match->player1_legs,
                    'player2_legs' => $match->player2_legs,
                    'winner_id' => $match->winner_id,
                    'loser_id' => $match->loser_id,
                    'status' => $match->status,
                    'status_label' => $match->status_label,
                    'status_badge_class' => $match->status_badge_class,
                ],
                'standings' => $standings,
            ]);
        }

        $winnerName = $match->winner ? $match->winner->name : 'Nerozhodně';
        return back()->with('success', "Výsledek zápasu {$match->round_name} byl zapsán! Skóre: {$p1Legs}:{$p2Legs} (Vítěz: {$winnerName})");
    }
}
