<?php

namespace App\Console\Commands;

use App\Models\GameMatch;
use App\Models\Player;
use App\Models\Tournament;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ResetPlayersCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'darts:reset-players 
                            {--force : Přeskočí potvrzovací dotaz} 
                            {--with-tournaments : Smaže také všechny turnaje}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Bezpečně smaže všechny hráče z databáze a vyčistí související vazby v zápasech a turnajích';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $playersCount = Player::count();

        if ($playersCount === 0) {
            $this->info('V tabulce "players" se nenachází žádní hráči. Není co mazat.');
            return Command::SUCCESS;
        }

        if (!$this->option('force')) {
            $confirmed = $this->confirm(
                "Opravdu si přejete smazat všech {$playersCount} hráčů včetně jejich odehraných zápasů a účastí v turnajích?",
                false
            );

            if (!$confirmed) {
                $this->warn('Operace byla zrušena uživatelem.');
                return Command::SUCCESS;
            }
        }

        $this->info("Zahajuji bezpečné mazání {$playersCount} hráčů a souvisejících vazeb...");

        try {
            DB::transaction(function () {
                $isSqlite = DB::getDriverName() === 'sqlite';

                if ($isSqlite) {
                    DB::statement('PRAGMA foreign_keys = OFF;');
                }

                // 1. Smazání všech zápasů (které odkazují na hráče)
                $deletedMatches = GameMatch::query()->delete();
                $this->line(" - Smazáno zápasů: {$deletedMatches}");

                // 2. Smazání vazeb v pivot tabulce tournament_players
                $deletedPivots = DB::table('tournament_players')->delete();
                $this->line(" - Smazáno účastí v turnajích (pivot): {$deletedPivots}");

                // 3. Reset winner_id v turnajích nebo smazání turnajů
                if ($this->option('with-tournaments')) {
                    $deletedTournaments = Tournament::query()->delete();
                    $this->line(" - Smazáno turnajů: {$deletedTournaments}");
                } else {
                    $updatedTournaments = Tournament::query()->update([
                        'winner_id' => null,
                        'status' => 'draft',
                    ]);
                    $this->line(" - Aktualizováno turnajů (reset vítěze a stavu na draft): {$updatedTournaments}");
                }

                // 4. Smazání všech hráčů
                $deletedPlayers = Player::query()->delete();
                $this->line(" - Smazáno hráčů: {$deletedPlayers}");

                if ($isSqlite) {
                    DB::statement('PRAGMA foreign_keys = ON;');
                }
            });

            $this->newLine();
            $this->info('✓ Všichni hráči a související vazby byli úspěšně a bezpečně odstraněni.');
            return Command::SUCCESS;

        } catch (\Throwable $e) {
            $this->error('Při mazání hráčů došlo k chybě: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }
}
