<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (Schema::hasTable('tournament_group_players') && !Schema::hasColumn('tournament_group_players', 'matches_drawn')) {
            Schema::table('tournament_group_players', function (Blueprint $table) {
                $table->integer('matches_drawn')->default(0)->after('matches_won');
            });
        }

        // Aktualizujeme existující zápasy se stavem 'scheduled' na nový výchozí stav 'pending'
        if (Schema::hasTable('game_matches')) {
            DB::table('game_matches')
                ->where('status', 'scheduled')
                ->update(['status' => 'pending']);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('tournament_group_players') && Schema::hasColumn('tournament_group_players', 'matches_drawn')) {
            Schema::table('tournament_group_players', function (Blueprint $table) {
                $table->dropColumn('matches_drawn');
            });
        }

        if (Schema::hasTable('game_matches')) {
            DB::table('game_matches')
                ->where('status', 'pending')
                ->update(['status' => 'scheduled']);
        }
    }
};
