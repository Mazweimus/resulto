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
        Schema::create('players', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('gender')->default('male'); // 'male' or 'female'
            $table->timestamps();
        });

        Schema::create('tournaments', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->date('date');
            $table->string('status')->default('draft'); // draft, ongoing, finished
            $table->string('game_mode')->default('501'); // 501, 301
            $table->string('checkout_mode')->default('double_out'); // double_out, single_out
            $table->string('format')->default('double_elimination'); // double_elimination, knockout, round_robin
            $table->integer('best_of_legs')->default(3);
            $table->foreignId('winner_id')->nullable()->constrained('players')->nullOnDelete();
            $table->foreignId('second_place_id')->nullable()->constrained('players')->nullOnDelete();
            $table->foreignId('third_place_id')->nullable()->constrained('players')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('tournament_players', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tournament_id')->constrained('tournaments')->cascadeOnDelete();
            $table->foreignId('player_id')->constrained('players')->cascadeOnDelete();
            $table->integer('seed')->nullable();
            $table->timestamps();
        });

        Schema::create('game_matches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tournament_id')->constrained('tournaments')->cascadeOnDelete();
            $table->string('bracket_type')->default('winners'); // 'winners' (A), 'losers' (B), 'grand_final'
            $table->integer('round')->default(1);
            $table->string('round_name')->nullable();
            $table->integer('match_number')->default(1);
            $table->foreignId('player1_id')->nullable()->constrained('players')->nullOnDelete();
            $table->foreignId('player2_id')->nullable()->constrained('players')->nullOnDelete();
            $table->foreignId('winner_id')->nullable()->constrained('players')->nullOnDelete();
            $table->foreignId('loser_id')->nullable()->constrained('players')->nullOnDelete();
            $table->integer('player1_legs')->default(0);
            $table->integer('player2_legs')->default(0);
            $table->string('status')->default('scheduled'); // scheduled, completed
            $table->foreignId('next_match_id')->nullable()->constrained('game_matches')->nullOnDelete();
            $table->integer('next_match_slot')->nullable(); // 1 or 2
            $table->foreignId('loser_match_id')->nullable()->constrained('game_matches')->nullOnDelete();
            $table->integer('loser_match_slot')->nullable(); // 1 or 2
            $table->boolean('is_reset_match')->default(false);
            $table->boolean('is_bye')->default(false);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('game_matches');
        Schema::dropIfExists('tournament_players');
        Schema::dropIfExists('tournaments');
        Schema::dropIfExists('players');
    }
};
