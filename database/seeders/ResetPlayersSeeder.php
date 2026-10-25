<?php

namespace Database\Seeders;

use App\Models\GameMatch;
use App\Models\Player;
use App\Models\Tournament;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ResetPlayersSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $isSqlite = DB::getDriverName() === 'sqlite';

        if ($isSqlite) {
            DB::statement('PRAGMA foreign_keys = OFF;');
        }

        GameMatch::query()->delete();
        DB::table('tournament_players')->delete();
        Tournament::query()->update(['winner_id' => null, 'status' => 'draft']);
        Player::query()->delete();

        if ($isSqlite) {
            DB::statement('PRAGMA foreign_keys = ON;');
        }
    }
}
