<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('tournament_groups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tournament_id')->constrained('tournaments')->cascadeOnDelete();
            $table->string('name', 100);
            $table->unsignedSmallInteger('order')->default(1);
            $table->string('stage', 50)->default('group_stage');
            $table->timestamps();

            $table->index(['tournament_id', 'order']);
        });

        Schema::create('tournament_group_players', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tournament_group_id')->constrained('tournament_groups')->cascadeOnDelete();
            $table->foreignId('player_id')->constrained('players')->cascadeOnDelete();
            $table->unsignedSmallInteger('seed')->nullable();
            $table->integer('points')->default(0);
            $table->integer('matches_played')->default(0);
            $table->integer('matches_won')->default(0);
            $table->integer('matches_lost')->default(0);
            $table->integer('legs_won')->default(0);
            $table->integer('legs_lost')->default(0);
            $table->timestamps();

            $table->unique(['tournament_group_id', 'player_id'], 'group_player_unique');
            $table->index(['tournament_group_id', 'points']);
        });

        if (Schema::hasTable('game_matches') && !Schema::hasColumn('game_matches', 'tournament_group_id')) {
            Schema::table('game_matches', function (Blueprint $table) {
                $table->foreignId('tournament_group_id')
                    ->nullable()
                    ->after('tournament_id')
                    ->constrained('tournament_groups')
                    ->cascadeOnDelete();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('game_matches') && Schema::hasColumn('game_matches', 'tournament_group_id')) {
            Schema::table('game_matches', function (Blueprint $table) {
                $table->dropForeign(['tournament_group_id']);
                $table->dropColumn('tournament_group_id');
            });
        }

        Schema::dropIfExists('tournament_group_players');
        Schema::dropIfExists('tournament_groups');
    }
};
