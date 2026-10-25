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
        Schema::table('tournaments', function (Blueprint $table) {
            $table->boolean('counts_for_ranking')->default(true)->after('format');
            $table->unsignedSmallInteger('season_year')->default((int) date('Y'))->after('counts_for_ranking');
            $table->index(['counts_for_ranking', 'season_year']);
        });

        // Pro existující turnaje doplníme season_year podle sloupce date
        DB::table('tournaments')->whereNotNull('date')->get()->each(function ($t) {
            $year = !empty($t->date) ? (int) date('Y', strtotime($t->date)) : (int) date('Y');
            DB::table('tournaments')->where('id', $t->id)->update([
                'counts_for_ranking' => true,
                'season_year' => $year,
            ]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tournaments', function (Blueprint $table) {
            $table->dropIndex(['counts_for_ranking', 'season_year']);
            $table->dropColumn(['counts_for_ranking', 'season_year']);
        });
    }
};
