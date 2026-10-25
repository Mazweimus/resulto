<?php

use App\Models\Player;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $players = Player::orderBy('id')->get();
        $byName = [];

        foreach ($players as $p) {
            $norm = mb_strtolower(trim($p->name));
            $byName[$norm][] = $p;
        }

        foreach ($byName as $name => $list) {
            if (count($list) > 1) {
                $primary = $list[0];

                for ($i = 1; $i < count($list); $i++) {
                    $dup = $list[$i];

                    DB::transaction(function () use ($primary, $dup) {
                        // 1. tournament_players pivot
                        $existingPivots = DB::table('tournament_players')->where('player_id', $dup->id)->get();
                        foreach ($existingPivots as $piv) {
                            $alreadyAttached = DB::table('tournament_players')
                                ->where('tournament_id', $piv->tournament_id)
                                ->where('player_id', $primary->id)
                                ->exists();
                            if (!$alreadyAttached) {
                                DB::table('tournament_players')->where('id', $piv->id)->update(['player_id' => $primary->id]);
                            } else {
                                DB::table('tournament_players')->where('id', $piv->id)->delete();
                            }
                        }

                        // 2. game_matches (player1_id, player2_id, winner_id, loser_id)
                        DB::table('game_matches')->where('player1_id', $dup->id)->update(['player1_id' => $primary->id]);
                        DB::table('game_matches')->where('player2_id', $dup->id)->update(['player2_id' => $primary->id]);
                        DB::table('game_matches')->where('winner_id', $dup->id)->update(['winner_id' => $primary->id]);
                        DB::table('game_matches')->where('loser_id', $dup->id)->update(['loser_id' => $primary->id]);

                        // 3. tournaments (winner_id, second_place_id, third_place_id)
                        DB::table('tournaments')->where('winner_id', $dup->id)->update(['winner_id' => $primary->id]);
                        DB::table('tournaments')->where('second_place_id', $dup->id)->update(['second_place_id' => $primary->id]);
                        DB::table('tournaments')->where('third_place_id', $dup->id)->update(['third_place_id' => $primary->id]);

                        // 4. tournament_standings
                        $dupStandings = DB::table('tournament_standings')->where('player_id', $dup->id)->get();
                        foreach ($dupStandings as $ds) {
                            $hasPrimary = DB::table('tournament_standings')
                                ->where('tournament_id', $ds->tournament_id)
                                ->where('player_id', $primary->id)
                                ->exists();
                            if (!$hasPrimary) {
                                DB::table('tournament_standings')->where('id', $ds->id)->update(['player_id' => $primary->id]);
                            } else {
                                DB::table('tournament_standings')->where('id', $ds->id)->delete();
                            }
                        }

                        // 5. Delete duplicate player
                        $dup->delete();
                    });
                }
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Deduplication cannot be reversed
    }
};
