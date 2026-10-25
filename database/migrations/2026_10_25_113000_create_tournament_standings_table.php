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
        Schema::create('tournament_standings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tournament_id')->constrained('tournaments')->cascadeOnDelete();
            $table->foreignId('player_id')->constrained('players')->cascadeOnDelete();
            $table->unsignedSmallInteger('rank_from');
            $table->unsignedSmallInteger('rank_to');
            $table->unsignedSmallInteger('points')->default(0);
            $table->timestamps();

            $table->unique(['tournament_id', 'player_id']);
            $table->index(['player_id', 'points']);
            $table->index(['tournament_id', 'rank_from']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tournament_standings');
    }
};
