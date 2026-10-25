<?php

namespace Tests\Feature;

use App\Models\GameMatch;
use App\Models\Player;
use App\Models\Tournament;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class DartsTournamentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_dashboard_page_loads_successfully_with_podium_and_history(): void
    {
        $response = $this->get('/');

        $response->assertStatus(200);
        $response->assertSee('Darts Dashboard');
        $response->assertSee('Historie turnajů & Medailové pódium', false);
        $response->assertSee('Prague Darts Open 2026');
        $response->assertSee('1. Místo', false);
        $response->assertSee('2. Místo', false);
        $response->assertSee('3. Místo', false);
        $response->assertSee('Zobrazit celý pavouk turnaje');
    }

    public function test_players_index_page_loads(): void
    {
        $response = $this->get('/players');

        $response->assertStatus(200);
        $response->assertSee('Správa hráčů');
        $response->assertSee('Luke Littler');
        $response->assertSee('Pohlaví');
        $response->assertSee('Muž');
        $response->assertSee('Přidat nového hráče');
    }

    public function test_players_create_page_loads(): void
    {
        $response = $this->get('/players/create');

        $response->assertStatus(200);
        $response->assertSee('Nový hráč');
        $response->assertSee('Registrace nového šipkaře');
    }

    public function test_new_player_can_be_created(): void
    {
        $response = $this->post('/players', [
            'name' => 'Karel Sedláček',
            'gender' => 'male',
        ]);

        $response->assertRedirect('/players');
        $this->assertDatabaseHas('players', [
            'name' => 'Karel Sedláček',
            'gender' => 'male',
        ]);
    }

    public function test_player_can_be_created_via_ajax_request(): void
    {
        $response = $this->postJson('/players', [
            'name' => 'Martin Schindler',
            'gender' => 'male',
        ]);

        $response->assertStatus(201);
        $response->assertJson([
            'success' => true,
            'player' => [
                'name' => 'Martin Schindler',
                'gender' => 'male',
                'gender_label' => 'Muž',
            ],
        ]);

        $this->assertDatabaseHas('players', [
            'name' => 'Martin Schindler',
            'gender' => 'male',
        ]);
    }

    public function test_player_can_be_edited_and_updated(): void
    {
        $player = Player::first();

        $editResponse = $this->get(route('players.edit', $player->id));
        $editResponse->assertStatus(200);
        $editResponse->assertSee($player->name);

        $updateResponse = $this->put(route('players.update', $player->id), [
            'name' => 'Luke The Littler',
            'gender' => 'male',
        ]);

        $updateResponse->assertRedirect('/players');
        $player->refresh();
        $this->assertEquals('Luke The Littler', $player->name);
        $this->assertEquals('male', $player->gender);
    }

    public function test_player_can_be_updated_via_ajax_request(): void
    {
        $player = Player::create([
            'name' => 'Original Name',
            'gender' => 'male',
        ]);

        $response = $this->putJson("/players/{$player->id}", [
            'name' => 'Updated Name',
            'gender' => 'female',
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'player' => [
                'id' => $player->id,
                'name' => 'Updated Name',
                'gender' => 'female',
                'gender_label' => 'Žena',
            ],
        ]);

        $this->assertDatabaseHas('players', [
            'id' => $player->id,
            'name' => 'Updated Name',
            'gender' => 'female',
        ]);
    }

    public function test_player_reset_artisan_command_safely_deletes_players(): void
    {
        $this->assertGreaterThan(0, Player::count());

        $exitCode = Artisan::call('darts:reset-players', ['--force' => true]);
        $this->assertEquals(0, $exitCode);

        $this->assertEquals(0, Player::count());
        $this->assertEquals(0, GameMatch::count());
    }

    public function test_tournaments_index_page_loads(): void
    {
        $response = $this->get('/tournaments');

        $response->assertStatus(200);
        $response->assertSee('Turnaje v šipkách');
        $response->assertSee('Czech Darts Masters 2026');
        $response->assertSee('Pavouk / Zápasy');
    }

    public function test_tournaments_create_page_loads_with_player_selection_table(): void
    {
        $response = $this->get('/tournaments/create');

        $response->assertStatus(200);
        $response->assertSee('Vytvořit nový turnaj');
        $response->assertSee('Vybraní hráči do turnaje');
        $response->assertSee('Počet hráčů:');
        $response->assertSee('Zatím nebyli vybráni žádní hráči');
        $response->assertSee('Vytvořit a přidat do turnaje');
        $response->assertSee('Seznam hráčů v databázi');
        $response->assertSee('Úspěšnost');
        $response->assertDontSee('Na 2 vítězné legy (Best of 3)');
    }

    public function test_tournament_creation_auto_fills_501_and_double_out_when_not_passed(): void
    {
        $playerIds = Player::take(8)->pluck('id')->toArray();

        // Odeslání bez game_mode, checkout_mode, date a best_of_legs a s klíčem 'players' namísto 'player_ids'
        $response = $this->post('/tournaments', [
            'name' => 'Auto Defaults Test 2026',
            'players' => $playerIds,
        ]);

        $tournament = Tournament::where('name', 'Auto Defaults Test 2026')->first();
        $this->assertNotNull($tournament);
        $this->assertEquals('501', $tournament->game_mode);
        $this->assertEquals('double_out', $tournament->checkout_mode);
        $this->assertEquals(3, $tournament->best_of_legs);
        $this->assertEquals('double_elimination', $tournament->format);
        $this->assertEquals(now()->toDateString(), $tournament->date->toDateString());
        $this->assertCount(8, $tournament->players);
        $response->assertRedirect(route('tournaments.play', $tournament->id));
    }

    public function test_double_elimination_tournament_creation_generates_all_brackets_and_redirects(): void
    {
        $playerIds = Player::take(8)->pluck('id')->toArray();

        $response = $this->post('/tournaments', [
            'name' => 'Double Elim Test 2026',
            'date' => '2026-06-01',
            'game_mode' => '501',
            'checkout_mode' => 'double_out',
            'format' => 'double_elimination',
            'best_of_legs' => 5,
            'player_ids' => $playerIds,
        ]);

        $tournament = Tournament::where('name', 'Double Elim Test 2026')->first();
        $this->assertNotNull($tournament);

        $response->assertRedirect(route('tournaments.play', $tournament->id));

        // Pro 8 hráčů v Double Elimination:
        // Winners Bracket: 4 + 2 + 1 = 7 zápasů
        // Losers Bracket: 2 + 2 + 1 + 1 = 6 zápasů
        // Grand Final: 2 zápasy (GF1 a GF2 Reset)
        // Celkem = 15 zápasů
        $this->assertEquals(7, $tournament->winnersMatches()->count());
        $this->assertEquals(6, $tournament->losersMatches()->count());
        $this->assertEquals(2, $tournament->grandFinalMatches()->count());
    }

    public function test_double_elimination_16_players_generation(): void
    {
        $extraPlayers = [];
        for ($i = 9; $i <= 16; $i++) {
            $extraPlayers[] = Player::create([
                'name' => "Extra Player {$i}",
                'gender' => 'male',
            ]);
        }

        $all16Ids = Player::take(16)->pluck('id')->toArray();
        $this->assertCount(16, $all16Ids);

        $tournament = Tournament::create([
            'name' => '16 Players Double Elim',
            'date' => '2026-06-15',
            'game_mode' => '501',
            'checkout_mode' => 'double_out',
            'format' => 'double_elimination',
            'best_of_legs' => 7,
            'status' => 'ongoing',
        ]);
        $tournament->players()->attach($all16Ids);
        $tournament->generateDoubleEliminationMatches($all16Ids);

        // 16 hráčů:
        // Winners Bracket: 8 + 4 + 2 + 1 = 15
        // Losers Bracket: 4 + 4 + 2 + 2 + 1 + 1 = 14
        // Grand Final: 2
        // Celkem = 31 zápasů
        $this->assertEquals(15, $tournament->winnersMatches()->count());
        $this->assertEquals(14, $tournament->losersMatches()->count());
        $this->assertEquals(2, $tournament->grandFinalMatches()->count());
    }

    public function test_match_draw_is_prevented(): void
    {
        $tournament = Tournament::where('name', 'Czech Darts Masters 2026')->first();
        $match = $tournament->matches()->where('status', 'scheduled')->whereNotNull('player1_id')->whereNotNull('player2_id')->first();

        $response = $this->put(route('matches.update', $match->id), [
            'player1_legs' => 2,
            'player2_legs' => 2,
            'status' => 'completed',
        ]);

        $response->assertSessionHas('error');
        $match->refresh();
        $this->assertEquals('scheduled', $match->status);
    }

    public function test_double_elimination_loser_drops_from_bracket_a_to_bracket_b(): void
    {
        $tournament = Tournament::where('name', 'Czech Darts Masters 2026')->first();

        // Čtvrtfinále A zápas 3
        $wbMatch3 = $tournament->matches()
            ->where('bracket_type', 'winners')
            ->where('round', 1)
            ->where('match_number', 3)
            ->first();

        $this->assertNotNull($wbMatch3);
        $this->assertNotNull($wbMatch3->player1_id);
        $this->assertNotNull($wbMatch3->player2_id);
        $this->assertNotNull($wbMatch3->loser_match_id);

        $p1 = $wbMatch3->player1_id;
        $p2 = $wbMatch3->player2_id;

        // Player 1 vyhrává 4:1 nad Player 2
        $response = $this->put(route('matches.update', $wbMatch3->id), [
            'player1_legs' => 4,
            'player2_legs' => 1,
            'status' => 'completed',
        ]);

        $response->assertSessionHas('success');
        $wbMatch3->refresh();

        $this->assertEquals('completed', $wbMatch3->status);
        $this->assertEquals($p1, $wbMatch3->winner_id);
        $this->assertEquals($p2, $wbMatch3->loser_id);

        // Ověření, že vítěz postoupil do Semifinále A (WB R2)
        $wbNext = $wbMatch3->nextMatch;
        $this->assertNotNull($wbNext);
        $this->assertEquals($p1, $wbNext->player1_id);

        // Ověření, že poražený propadl do Pavouka B (LB R1)
        $lbTarget = $wbMatch3->loserMatch;
        $this->assertNotNull($lbTarget);
        $this->assertEquals($p2, $lbTarget->player1_id);
    }

    public function test_grand_final_winner_from_a_wins_tournament_immediately(): void
    {
        $tournament = Tournament::where('name', 'Czech Darts Masters 2026')->first();
        $gf1 = $tournament->matches()->where('bracket_type', 'grand_final')->where('round', 1)->first();

        $pA = Player::find(1);
        $pB = Player::find(2);

        $gf1->update([
            'player1_id' => $pA->id,
            'player2_id' => $pB->id,
            'status' => 'scheduled',
        ]);

        // Hráč z A vyhrává Finále 1 (např. 4:2)
        $this->put(route('matches.update', $gf1->id), [
            'player1_legs' => 4,
            'player2_legs' => 2,
            'status' => 'completed',
        ]);

        $tournament->refresh();
        $this->assertEquals('finished', $tournament->status);
        $this->assertEquals($pA->id, $tournament->winner_id);
        $this->assertEquals($pB->id, $tournament->second_place_id);
    }

    public function test_grand_final_winner_from_b_triggers_bracket_reset_match(): void
    {
        $tournament = Tournament::where('name', 'Czech Darts Masters 2026')->first();
        $gf1 = $tournament->matches()->where('bracket_type', 'grand_final')->where('round', 1)->first();

        $pA = Player::find(1);
        $pB = Player::find(2);

        $gf1->update([
            'player1_id' => $pA->id,
            'player2_id' => $pB->id,
            'status' => 'scheduled',
        ]);

        // Hráč z B vyhrává Finále 1 (např. 2:4)
        $this->put(route('matches.update', $gf1->id), [
            'player1_legs' => 2,
            'player2_legs' => 4,
            'status' => 'completed',
        ]);

        $tournament->refresh();
        // Turnaj NESMÍ být ukončen, protože nastal Bracket Reset!
        $this->assertEquals('ongoing', $tournament->status);

        // Finále 2 (Reset zápas) je nyní připraveno k odehrání
        $gf2 = $tournament->matches()->where('bracket_type', 'grand_final')->where('is_reset_match', true)->first();
        $this->assertNotNull($gf2);
        $this->assertEquals($pB->id, $gf2->player1_id);
        $this->assertEquals($pA->id, $gf2->player2_id);
        $this->assertEquals('scheduled', $gf2->status);

        // Nyní odehrajeme Finále 2 – rozhodující zápas
        $this->put(route('matches.update', $gf2->id), [
            'player1_legs' => 4,
            'player2_legs' => 3,
            'status' => 'completed',
        ]);

        $tournament->refresh();
        $this->assertEquals('finished', $tournament->status);
        $this->assertEquals($pB->id, $tournament->winner_id);
        $this->assertEquals($pA->id, $tournament->second_place_id);
    }

    public function test_double_elimination_with_6_players_properly_distributes_byes_and_advances(): void
    {
        $sixPlayerIds = Player::take(6)->pluck('id')->toArray();
        $this->assertCount(6, $sixPlayerIds);

        $response = $this->post('/tournaments', [
            'name' => 'Six Players Double Elim',
            'date' => '2026-07-01',
            'game_mode' => '501',
            'checkout_mode' => 'double_out',
            'format' => 'double_elimination',
            'best_of_legs' => 5,
            'player_ids' => $sixPlayerIds,
        ]);

        $tournament = Tournament::where('name', 'Six Players Double Elim')->first();
        $this->assertNotNull($tournament);
        $response->assertRedirect(route('tournaments.play', $tournament->id));

        // Pro 6 hráčů v pavouku pro 8 pozic:
        // 4 zápasy čtvrtfinále (WB R1)
        $r1Matches = $tournament->matches()
            ->where('bracket_type', 'winners')
            ->where('round', 1)
            ->orderBy('match_number')
            ->get();

        $this->assertCount(4, $r1Matches);

        // Požadavek: V KAŽDÉM zápase 1. kola je VŽDY ALASPOŇ JEDEN reálný hráč!
        // Žádný zápas nesmí mít oba sloty NULL!
        foreach ($r1Matches as $m) {
            $this->assertNotNull($m->player1_id, "Zápas #{$m->match_number} v 1. kole nesmí mít prázdný slot player1!");
        }

        // Přesně 2 zápasy mají volný los (BYE) a 2 zápasy mají plnou dvojici
        $byeMatches = $r1Matches->where('is_bye', true);
        $fullMatches = $r1Matches->where('is_bye', false);

        $this->assertCount(2, $byeMatches);
        $this->assertCount(2, $fullMatches);

        // U BYE zápasů: player1_id je hráč, player2_id je NULL
        foreach ($byeMatches as $bm) {
            $this->assertNotNull($bm->player1_id);
            $this->assertNull($bm->player2_id);
            // Automaticky vyhodnoceno: status = completed, vítěz je přítomen, žádný poražený
            $this->assertEquals('completed', $bm->status);
            $this->assertEquals($bm->player1_id, $bm->winner_id);
            $this->assertNull($bm->loser_id);

            // Ověření, že vítěz byl postoupen do Semifinále (WB R2)
            $this->assertNotNull($bm->next_match_id);
            $nextMatch = GameMatch::find($bm->next_match_id);
            $this->assertNotNull($nextMatch);
            if ($bm->next_match_slot === 1) {
                $this->assertEquals($bm->winner_id, $nextMatch->player1_id);
            } else {
                $this->assertEquals($bm->winner_id, $nextMatch->player2_id);
            }
        }

        // Ověření, že do Losers Bracketu se v případě BYE nepropsal žádný neexistující hráč
        $lbR1Matches = $tournament->matches()
            ->where('bracket_type', 'losers')
            ->where('round', 1)
            ->get();

        foreach ($lbR1Matches as $lbm) {
            $this->assertNull($lbm->player1_id); // Slot 1 čekal na BYE -> zůstal null, ne žádná neplatná hodnota
        }

        // Zobrazení v Blade šabloně: obsahuje texty o volném losu
        $playPage = $this->get(route('tournaments.play', $tournament->id));
        $playPage->assertStatus(200);
        $playPage->assertSee('Volný los (Postup)');
        $playPage->assertSee('Volný los (BYE)');
    }

    public function test_double_elimination_bye_distribution_for_various_player_counts(): void
    {
        // Vytvoříme dostatek hráčů pro testování
        for ($i = 9; $i <= 32; $i++) {
            Player::firstOrCreate(
                ['name' => "Player {$i}"],
                ['gender' => 'male']
            );
        }

        foreach ([3, 5, 7, 10, 14, 20] as $count) {
            $ids = Player::take($count)->pluck('id')->toArray();
            $this->assertCount($count, $ids);

            $tournament = Tournament::create([
                'name' => "Test {$count} Players",
                'date' => '2026-07-03',
                'game_mode' => '501',
                'checkout_mode' => 'double_out',
                'format' => 'double_elimination',
                'best_of_legs' => 5,
                'status' => 'ongoing',
            ]);
            $tournament->players()->attach($ids);
            $tournament->generateDoubleEliminationMatches($ids);

            $r1Matches = $tournament->matches()
                ->where('bracket_type', 'winners')
                ->where('round', 1)
                ->get();

            // Ověření, že ŽÁDNÝ zápas v 1. kole nemá oba sloty NULL
            // a každý má reálného hráče v player1_id
            foreach ($r1Matches as $m) {
                $this->assertNotNull(
                    $m->player1_id,
                    "Při {$count} hráčích má zápas #{$m->match_number} v 1. kole prázdný player1!"
                );
            }

            // Ověření, že počet BYE v 1. kole přesně odpovídá (velikost_pavouka - počet hráčů)
            $bracketSize = 4;
            while ($bracketSize < $count) {
                $bracketSize *= 2;
            }
            $expectedByes = $bracketSize - $count;
            $actualByes = $r1Matches->where('is_bye', true)->count();
            $this->assertEquals(
                $expectedByes,
                $actualByes,
                "Při {$count} hráčích nesedí počet BYE zápasů v 1. kole!"
            );
        }
    }

    public function test_playing_6_player_double_elimination_cascades_lb_byes(): void
    {
        $sixPlayerIds = Player::take(6)->pluck('id')->toArray();

        $tournament = Tournament::create([
            'name' => 'Six Players Playthrough',
            'date' => '2026-07-02',
            'game_mode' => '501',
            'checkout_mode' => 'double_out',
            'format' => 'double_elimination',
            'best_of_legs' => 5,
            'status' => 'ongoing',
        ]);
        $tournament->players()->attach($sixPlayerIds);
        $tournament->generateDoubleEliminationMatches($sixPlayerIds);

        // Najdeme reálný zápas v 1. kole WB
        $realMatch = $tournament->matches()
            ->where('bracket_type', 'winners')
            ->where('round', 1)
            ->where('is_bye', false)
            ->first();

        $this->assertNotNull($realMatch);
        $this->assertNotNull($realMatch->player1_id);
        $this->assertNotNull($realMatch->player2_id);

        $p1 = $realMatch->player1_id;
        $p2 = $realMatch->player2_id;

        // Odehrajeme zápas: P1 vyhrává 3:1 nad P2
        $this->put(route('matches.update', $realMatch->id), [
            'player1_legs' => 3,
            'player2_legs' => 1,
            'status' => 'completed',
        ]);

        $realMatch->refresh();
        $this->assertEquals('completed', $realMatch->status);
        $this->assertEquals($p1, $realMatch->winner_id);
        $this->assertEquals($p2, $realMatch->loser_id);

        // Loser P2 propadl do LB R1 zápasu, kde druhý slot byl BYE
        // Tento LB zápas se musel automaticky vyhodnotit jako BYE a posunout P2 do LB R2!
        $lbTarget = GameMatch::find($realMatch->loser_match_id);
        $this->assertNotNull($lbTarget);
        $this->assertEquals('completed', $lbTarget->status);
        $this->assertTrue($lbTarget->is_bye);
        $this->assertEquals($p2, $lbTarget->winner_id);

        // Hráč P2 je nyní v LB R2
        $this->assertNotNull($lbTarget->next_match_id);
        $lbR2Match = GameMatch::find($lbTarget->next_match_id);
        $this->assertNotNull($lbR2Match);
        $this->assertTrue(
            $lbR2Match->player1_id === $p2 || $lbR2Match->player2_id === $p2,
            "Poražený hráč se nezasekl v LB R1, ale postoupil přes volný los do LB R2!"
        );
    }

    public function test_final_standings_calculation_and_ui_table_without_avatars(): void
    {
        $tournament = Tournament::where('name', 'Prague Darts Open 2026')->first();
        $this->assertNotNull($tournament);
        $this->assertEquals('finished', $tournament->status);

        // Ověření výpočtu pořadí
        $standings = (new \App\Services\TournamentService())->calculateStandings($tournament);
        $this->assertCount(8, $standings);

        $this->assertEquals('1.', $standings[0]['rank']);
        $this->assertEquals('Luke Littler', $standings[0]['player_name']);

        $this->assertEquals('2.', $standings[1]['rank']);
        $this->assertEquals('Michael Smith', $standings[1]['player_name']);

        $this->assertEquals('3.', $standings[2]['rank']);
        $this->assertEquals('Luke Humphries', $standings[2]['player_name']);

        $this->assertEquals('4.', $standings[3]['rank']);
        $this->assertEquals('Nathan Aspinall', $standings[3]['player_name']);

        // Dělená místa: 5.–6. a 7.–8.
        $this->assertEquals('5.–6.', $standings[4]['rank']);
        $this->assertEquals('Gerwyn Price', $standings[4]['player_name']);
        $this->assertEquals('5.–6.', $standings[5]['rank']);
        $this->assertEquals('Michael van Gerwen', $standings[5]['player_name']);
        $this->assertEquals('7.–8.', $standings[6]['rank']);
        $this->assertEquals('Rob Cross', $standings[6]['player_name']);
        $this->assertEquals('7.–8.', $standings[7]['rank']);
        $this->assertEquals('Peter Wright', $standings[7]['player_name']);

        // Ověření zobrazení v Blade šabloně
        $response = $this->get(route('tournaments.play', $tournament->id));
        $response->assertStatus(200);
        $response->assertSee('Konečné výsledky turnaje');
        $response->assertSee('Pořadí');
        $response->assertSee('Jméno hráče');
        $response->assertSee('5.–6.');
        $response->assertSee('7.–8.');

        // STRIKTNÍ POŽADAVEK NA DESIGN: Ve výsledkové tabulce (#results-block table)
        // nesmí být ŽÁDNÉ <img> ani avatary
        $html = $response->getContent();
        preg_match('/<div[^>]*id="results-block"[^>]*>(.*?)<\/div>\s*<\/div>\s*<\/div>/s', $html, $matches);
        $this->assertNotEmpty($matches, 'Výsledkový blok #results-block byl nalezen v UI');
        $resultsBlockHtml = $matches[0];
        $this->assertStringNotContainsString('<img', $resultsBlockHtml, 'Výsledková tabulka nesmí obsahovat žádné <img> avatary!');
    }

    public function test_backward_editing_of_match_updates_standings_and_downstream_matches(): void
    {
        $tournament = Tournament::where('name', 'Prague Darts Open 2026')->first();
        $this->assertEquals('finished', $tournament->status);

        // Najdeme Grand Finále 1
        $gf1 = $tournament->matches()->where('bracket_type', 'grand_final')->where('round', 1)->first();
        $this->assertNotNull($gf1);
        $this->assertEquals('completed', $gf1->status);

        $oldWinner = $gf1->winner_id;
        $oldLoser = $gf1->loser_id;

        // Administrátor provede zpětnou editaci Grand Finále:
        // Místo hráče z A vyhraje hráč z B (tzn. nastává Bracket Reset a turnaj přestává být kompletní!)
        $response = $this->put(route('matches.update', $gf1->id), [
            'player1_legs' => 1,
            'player2_legs' => 3,
            'status' => 'completed',
        ]);

        $response->assertSessionHas('success');
        $gf1->refresh();
        $this->assertEquals($oldLoser, $gf1->winner_id);
        $this->assertEquals($oldWinner, $gf1->loser_id);

        $tournament->refresh();
        // Turnaj nyní není kompletní, protože hráč z B vyhrál GF1 a musí se odehrát GF2 Reset!
        $this->assertEquals('ongoing', $tournament->status);
        $this->assertNull($tournament->winner_id);

        // Zkontrolujeme, že GF2 byl odemčen a čeká na odehrání
        $gf2 = $tournament->matches()->where('bracket_type', 'grand_final')->where('is_reset_match', true)->first();
        $this->assertNotNull($gf2);
        $this->assertEquals('scheduled', $gf2->status);

        // Nyní odehrajeme GF2: hráč z B vyhrává i GF2
        $this->put(route('matches.update', $gf2->id), [
            'player1_legs' => 3,
            'player2_legs' => 2,
            'status' => 'completed',
        ]);

        $tournament->refresh();
        $this->assertEquals('finished', $tournament->status);
        $this->assertEquals($oldLoser, $tournament->winner_id); // Nový celkový šampión!

        // Ověříme, že výsledková tabulka se automaticky přepočítala s novým vítězem
        $standings = (new \App\Services\TournamentService())->calculateStandings($tournament);
        $this->assertEquals('1.', $standings[0]['rank']);
        $this->assertEquals($oldLoser, $standings[0]['player']->id);
        $this->assertEquals('2.', $standings[1]['rank']);
        $this->assertEquals($oldWinner, $standings[1]['player']->id);
    }

    public function test_auth_login_and_logout(): void
    {
        $response = $this->get('/login');
        $response->assertStatus(200);

        $loginResponse = $this->post('/login', [
            'email' => 'admin@darts.cz',
            'password' => 'password',
        ]);

        $loginResponse->assertRedirect('/');
        $this->assertAuthenticated();

        $logoutResponse = $this->post('/logout');
        $logoutResponse->assertRedirect('/login');
        $this->assertGuest();
    }

    public function test_tournaments_create_page_contains_free_elimination_option_and_bracket_size(): void
    {
        $response = $this->get('/tournaments/create');

        $response->assertStatus(200);
        $response->assertSee('FREE Oneway Elimination');
        $response->assertSee('FREE Double Elimination');
        $response->assertDontSee('value="free_elimination"', false);
        $response->assertSee('free-bracket-size-wrapper');
        $response->assertSee('bracket-size-select');
        $response->assertSee('free-elimination-info-card');
    }

    public function test_free_elimination_creation_generates_empty_skeleton_and_redirects(): void
    {
        $response = $this->post('/tournaments', [
            'name' => 'Free Oneway 8 Test',
            'format' => 'free_oneway_elimination',
            'bracket_size' => 8,
        ]);

        $tournament = Tournament::where('name', 'Free Oneway 8 Test')->first();
        $this->assertNotNull($tournament);
        $this->assertEquals('in_progress', $tournament->status);
        $this->assertEquals('free_oneway_elimination', $tournament->format);
        $this->assertEquals(8, $tournament->bracket_size);
        $this->assertFalse($tournament->is_seeding_finalized);
        $response->assertRedirect(route('tournaments.play', $tournament->id));

        // 8 hráčů v oneway -> 7 zápasů ve winners + 1 zápas o 3. místo = 8 zápasů
        $this->assertEquals(8, $tournament->matches()->count());

        $r1Matches = $tournament->matches()->where('bracket_type', 'winners')->where('round', 1)->orderBy('match_number')->get();
        $this->assertCount(4, $r1Matches);

        foreach ($r1Matches as $m) {
            $this->assertNull($m->player1_id);
            $this->assertNull($m->player2_id);
            $this->assertFalse($m->is_bye);
            $this->assertEquals('scheduled', $m->status);
            $this->assertNotNull($m->next_match_id);
        }
    }

    public function test_free_elimination_bracket_sizes_16_and_32_skeleton(): void
    {
        $this->post('/tournaments', [
            'name' => 'Free Oneway 16 Test',
            'format' => 'free_oneway_elimination',
            'bracket_size' => 16,
        ]);

        $t16 = Tournament::where('name', 'Free Oneway 16 Test')->first();
        $this->assertNotNull($t16);
        $this->assertEquals(16, $t16->matches()->count()); // 15 WB + 1 third place

        $this->post('/tournaments', [
            'name' => 'Free Oneway 32 Test',
            'format' => 'free_oneway_elimination',
            'bracket_size' => 32,
        ]);

        $t32 = Tournament::where('name', 'Free Oneway 32 Test')->first();
        $this->assertNotNull($t32);
        $this->assertEquals(32, $t32->matches()->count()); // 31 WB + 1 third place
    }

    public function test_free_elimination_assign_slot_and_clear_slot_ajax(): void
    {
        $tournament = Tournament::create([
            'name' => 'Free Elim Slot Assign Test',
            'format' => 'free_oneway_elimination',
            'bracket_size' => 8,
            'is_seeding_finalized' => false,
            'status' => 'in_progress',
        ]);
        (new \App\Services\TournamentService())->generateFreeOnewayBracket($tournament, 8);

        $players = Player::take(2)->get();
        $r1Match = $tournament->matches()->where('bracket_type', 'winners')->where('round', 1)->first();

        // Přiřazení hráče 1 do slotu 1
        $response = $this->postJson(route('tournaments.assignSlot', $tournament->id), [
            'match_id' => $r1Match->id,
            'slot' => 1,
            'player_id' => $players[0]->id,
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'seeding_status' => [
                'assigned_count' => 1,
            ],
        ]);

        $r1Match->refresh();
        $this->assertEquals($players[0]->id, $r1Match->player1_id);

        // Vymazání hráče ze slotu 1
        $clearResponse = $this->postJson(route('tournaments.assignSlot', $tournament->id), [
            'match_id' => $r1Match->id,
            'slot' => 1,
            'player_id' => null,
        ]);

        $clearResponse->assertStatus(200);
        $clearResponse->assertJson([
            'success' => true,
            'seeding_status' => [
                'assigned_count' => 0,
            ],
        ]);

        $r1Match->refresh();
        $this->assertNull($r1Match->player1_id);
    }

    public function test_free_elimination_prevents_duplicate_player_assignment(): void
    {
        $tournament = Tournament::create([
            'name' => 'Free Elim Duplicate Test',
            'format' => 'free_oneway_elimination',
            'bracket_size' => 8,
            'is_seeding_finalized' => false,
            'status' => 'in_progress',
        ]);
        (new \App\Services\TournamentService())->generateFreeOnewayBracket($tournament, 8);

        $player = Player::first();
        $r1Matches = $tournament->matches()->where('bracket_type', 'winners')->where('round', 1)->orderBy('match_number')->get();

        // Nasadíme do zápasu 1, slot 1
        $this->postJson(route('tournaments.assignSlot', $tournament->id), [
            'match_id' => $r1Matches[0]->id,
            'slot' => 1,
            'player_id' => $player->id,
        ])->assertStatus(200);

        // Pokus o nasazení stejného hráče do zápasu 2, slot 1 -> selže
        $failResponse1 = $this->postJson(route('tournaments.assignSlot', $tournament->id), [
            'match_id' => $r1Matches[1]->id,
            'slot' => 1,
            'player_id' => $player->id,
        ]);
        $failResponse1->assertStatus(422);
        $failResponse1->assertJson(['success' => false]);

        // Pokus o nasazení stejného hráče do stejného zápasu do slotu 2 -> selže
        $failResponse2 = $this->postJson(route('tournaments.assignSlot', $tournament->id), [
            'match_id' => $r1Matches[0]->id,
            'slot' => 2,
            'player_id' => $player->id,
        ]);
        $failResponse2->assertStatus(422);
        $failResponse2->assertJson(['success' => false]);
    }

    public function test_free_elimination_finalize_seeding_validates_min_players_and_empty_matches(): void
    {
        $tournament = Tournament::create([
            'name' => 'Free Elim Finalize Validation Test',
            'format' => 'free_oneway_elimination',
            'bracket_size' => 8,
            'is_seeding_finalized' => false,
            'status' => 'in_progress',
        ]);
        (new \App\Services\TournamentService())->generateFreeOnewayBracket($tournament, 8);

        $players = Player::take(6)->get();
        $r1Matches = $tournament->matches()->where('bracket_type', 'winners')->where('round', 1)->orderBy('match_number')->get();

        // 1. Méně než 5 hráčů (např. 4 hráči v různých zápasech) -> selže
        for ($i = 0; $i < 4; $i++) {
            $r1Matches[$i]->update(['player1_id' => $players[$i]->id]);
        }
        $resp1 = $this->postJson(route('tournaments.finalizeSeeding', $tournament->id));
        $resp1->assertStatus(422);
        $resp1->assertJson(['success' => false]);

        // 2. 5 hráčů, ale 1 zápas je zcela prázdný (M0 má 2, M1 má 2, M2 má 1, M3 má 0) -> selže
        $r1Matches[0]->update(['player1_id' => $players[0]->id, 'player2_id' => $players[1]->id]);
        $r1Matches[1]->update(['player1_id' => $players[2]->id, 'player2_id' => $players[3]->id]);
        $r1Matches[2]->update(['player1_id' => $players[4]->id, 'player2_id' => null]);
        $r1Matches[3]->update(['player1_id' => null, 'player2_id' => null]);

        $resp2 = $this->postJson(route('tournaments.finalizeSeeding', $tournament->id));
        $resp2->assertStatus(422);
        $resp2->assertJson(['success' => false]);

        // 3. Správné rozložení 5 hráčů: M0 má 2, M1 má 1, M2 má 1, M3 má 1 -> projde
        $r1Matches[1]->update(['player2_id' => null]);
        $r1Matches[3]->update(['player1_id' => $players[3]->id]);

        $resp3 = $this->postJson(route('tournaments.finalizeSeeding', $tournament->id));
        $resp3->assertStatus(200);
        $resp3->assertJson(['success' => true]);

        $tournament->refresh();
        $this->assertTrue($tournament->is_seeding_finalized);
    }

    public function test_free_elimination_finalize_seeding_evaluates_byes_and_advances(): void
    {
        $tournament = Tournament::create([
            'name' => 'Free Elim 5 Players Byes Test',
            'format' => 'free_oneway_elimination',
            'bracket_size' => 8,
            'is_seeding_finalized' => false,
            'status' => 'in_progress',
        ]);
        (new \App\Services\TournamentService())->generateFreeOnewayBracket($tournament, 8);

        $players = Player::take(5)->get();
        $r1Matches = $tournament->matches()->where('bracket_type', 'winners')->where('round', 1)->orderBy('match_number')->get();

        // M0: P0 vs P1
        $r1Matches[0]->update(['player1_id' => $players[0]->id, 'player2_id' => $players[1]->id]);
        // M1: P2 vs null (BYE)
        $r1Matches[1]->update(['player1_id' => $players[2]->id, 'player2_id' => null]);
        // M2: P3 vs null (BYE)
        $r1Matches[2]->update(['player1_id' => $players[3]->id, 'player2_id' => null]);
        // M3: P4 vs null (BYE)
        $r1Matches[3]->update(['player1_id' => $players[4]->id, 'player2_id' => null]);

        $response = $this->postJson(route('tournaments.finalizeSeeding', $tournament->id));
        $response->assertStatus(200);

        $tournament->refresh();
        $this->assertTrue($tournament->is_seeding_finalized);

        // Zápas 0: reálný zápas
        $r1M0 = $r1Matches[0]->fresh();
        $this->assertFalse($r1M0->is_bye);
        $this->assertEquals('scheduled', $r1M0->status);

        // Zápasy 1, 2, 3: BYE zápasy s automatickým postupem
        $r1M1 = $r1Matches[1]->fresh();
        $this->assertTrue($r1M1->is_bye);
        $this->assertEquals('completed', $r1M1->status);
        $this->assertEquals($players[2]->id, $r1M1->winner_id);

        $r1M2 = $r1Matches[2]->fresh();
        $this->assertTrue($r1M2->is_bye);
        $this->assertEquals('completed', $r1M2->status);
        $this->assertEquals($players[3]->id, $r1M2->winner_id);

        $r1M3 = $r1Matches[3]->fresh();
        $this->assertTrue($r1M3->is_bye);
        $this->assertEquals('completed', $r1M3->status);
        $this->assertEquals($players[4]->id, $r1M3->winner_id);

        // Round 2
        $r2Matches = $tournament->matches()->where('bracket_type', 'winners')->where('round', 2)->orderBy('match_number')->get();
        // SF1 (M1 v R2): Slot 1 čeká na vítěze Zápasu 0, Slot 2 má Hráče 2
        $this->assertNull($r2Matches[0]->player1_id);
        $this->assertEquals($players[2]->id, $r2Matches[0]->player2_id);

        // SF2 (M2 v R2): Slot 1 má Hráče 3, Slot 2 má Hráče 4 -> zápas je rovnou připraven!
        $this->assertEquals($players[3]->id, $r2Matches[1]->player1_id);
        $this->assertEquals($players[4]->id, $r2Matches[1]->player2_id);
        $this->assertEquals('scheduled', $r2Matches[1]->status);
    }

    public function test_free_elimination_can_be_played_to_completion_with_standings(): void
    {
        $tournament = Tournament::create([
            'name' => 'Knockout Playthrough Full',
            'format' => 'single_elimination',
            'bracket_size' => 8,
            'is_seeding_finalized' => true,
            'status' => 'in_progress',
        ]);

        $players = Player::take(4)->get();
        // SF1
        $sf1 = GameMatch::create([
            'tournament_id' => $tournament->id,
            'bracket_type' => 'winners',
            'round' => 1,
            'round_name' => 'Semifinále',
            'match_number' => 1,
            'player1_id' => $players[0]->id,
            'player2_id' => $players[1]->id,
            'status' => 'scheduled',
        ]);
        // SF2
        $sf2 = GameMatch::create([
            'tournament_id' => $tournament->id,
            'bracket_type' => 'winners',
            'round' => 1,
            'round_name' => 'Semifinále',
            'match_number' => 2,
            'player1_id' => $players[2]->id,
            'player2_id' => $players[3]->id,
            'status' => 'scheduled',
        ]);
        // Final
        $final = GameMatch::create([
            'tournament_id' => $tournament->id,
            'bracket_type' => 'winners',
            'round' => 2,
            'round_name' => 'Finále',
            'match_number' => 1,
            'status' => 'scheduled',
        ]);
        $sf1->update(['next_match_id' => $final->id, 'next_match_slot' => 1]);
        $sf2->update(['next_match_id' => $final->id, 'next_match_slot' => 2]);

        // Odehrajeme SF1: P0 vyhraje nad P1
        $this->put(route('matches.update', $sf1->id), [
            'player1_legs' => 2,
            'player2_legs' => 0,
            'status' => 'completed',
        ]);

        // Odehrajeme SF2: P2 vyhraje nad P3
        $this->put(route('matches.update', $sf2->id), [
            'player1_legs' => 2,
            'player2_legs' => 1,
            'status' => 'completed',
        ]);

        $final->refresh();
        $this->assertEquals($players[0]->id, $final->player1_id);
        $this->assertEquals($players[2]->id, $final->player2_id);

        // Odehrajeme Finále: P0 vyhraje nad P2
        $this->put(route('matches.update', $final->id), [
            'player1_legs' => 2,
            'player2_legs' => 0,
            'status' => 'completed',
        ]);

        $tournament->refresh();
        $this->assertEquals('finished', $tournament->status);
        $this->assertEquals($players[0]->id, $tournament->winner_id);
        $this->assertEquals($players[2]->id, $tournament->second_place_id);

        // Konečné pořadí
        $standings = (new \App\Services\TournamentService())->calculateStandings($tournament);
        $this->assertEquals('1.', $standings[0]['rank']);
        $this->assertEquals($players[0]->name, $standings[0]['player_name']);
        $this->assertEquals('2.', $standings[1]['rank']);
        $this->assertEquals($players[2]->name, $standings[1]['player_name']);
        $this->assertEquals('3.–4.', $standings[2]['rank']);
        $this->assertEquals('3.–4.', $standings[3]['rank']);
    }

    public function test_free_elimination_creates_new_player_when_name_string_provided(): void
    {
        $response = $this->post('/tournaments', [
            'name' => 'Free Oneway New Names Test',
            'format' => 'free_oneway_elimination',
            'bracket_size' => 8,
            'slots' => [
                'Brand New Darts Master 1',
                'Brand New Darts Master 2',
            ],
        ]);

        $tournament = Tournament::where('name', 'Free Oneway New Names Test')->first();
        $this->assertNotNull($tournament);
        $this->assertEquals('in_progress', $tournament->status);

        $this->assertDatabaseHas('players', ['name' => 'Brand New Darts Master 1']);
        $this->assertDatabaseHas('players', ['name' => 'Brand New Darts Master 2']);

        $m = $tournament->matches()->where('bracket_type', 'winners')->first();
        $this->assertNotNull($m->player1_id);
        $this->assertNotNull($m->player2_id);
        $this->assertEquals('Brand New Darts Master 1', $m->player1->name);
        $this->assertEquals('Brand New Darts Master 2', $m->player2->name);
    }

    public function test_tournaments_create_view_contains_both_new_free_options(): void
    {
        $response = $this->get(route('tournaments.create'));
        $response->assertStatus(200);
        $response->assertSee('free_oneway_elimination');
        $response->assertSee('free_double_elimination');
        $response->assertSee('FREE Oneway Elimination (Single + o 3. místo)');
        $response->assertSee('FREE Double Elimination (s Pavoukem B a Finále)');
    }

    public function test_free_oneway_elimination_creation_generates_skeleton_and_third_place_match(): void
    {
        $response = $this->post('/tournaments', [
            'name' => 'Free Oneway 8 Skeleton Test',
            'format' => 'free_oneway_elimination',
            'bracket_size' => 8,
        ]);

        $tournament = Tournament::where('name', 'Free Oneway 8 Skeleton Test')->first();
        $this->assertNotNull($tournament);
        $response->assertRedirect(route('tournaments.play', $tournament->id));

        $this->assertEquals('free_oneway_elimination', $tournament->format);
        $this->assertEquals('free_oneway_elimination', $tournament->type);
        $this->assertEquals(8, $tournament->bracket_size);
        $this->assertFalse($tournament->is_seeding_finalized);
        $this->assertEquals('in_progress', $tournament->status);

        // Zápas o 3. místo
        $tpMatch = $tournament->thirdPlaceMatch;
        $this->assertNotNull($tpMatch);
        $this->assertTrue($tpMatch->is_third_place);
        $this->assertEquals('third_place', $tpMatch->bracket_type);
        $this->assertEquals('Zápas o 3. a 4. místo', $tpMatch->round_name);
        $this->assertNull($tpMatch->player1_id);
        $this->assertNull($tpMatch->player2_id);

        // Winners bracket: 4 v R1, 2 v R2 (Semis), 1 v R3 (Final)
        $wbMatches = $tournament->winnersMatches;
        $this->assertCount(7, $wbMatches);
        $this->assertCount(4, $wbMatches->where('round', 1));
        $this->assertCount(2, $wbMatches->where('round', 2));
        $this->assertCount(1, $wbMatches->where('round', 3));

        // Semifinále mají napojení na zápas o 3. místo pro poražené
        $semi1 = $wbMatches->where('round', 2)->where('match_number', 1)->first();
        $semi2 = $wbMatches->where('round', 2)->where('match_number', 2)->first();

        $this->assertEquals($tpMatch->id, $semi1->loser_match_id);
        $this->assertEquals(1, $semi1->loser_match_slot);
        $this->assertEquals($tpMatch->id, $semi2->loser_match_id);
        $this->assertEquals(2, $semi2->loser_match_slot);
    }

    public function test_free_oneway_elimination_playthrough_with_mandatory_third_place_match_and_standings(): void
    {
        $tournament = Tournament::create([
            'name' => 'Free Oneway 6 Players Full Playthrough',
            'format' => 'free_oneway_elimination',
            'bracket_size' => 8,
            'is_seeding_finalized' => false,
            'status' => 'in_progress',
        ]);
        (new \App\Services\TournamentService())->generateFreeOnewayBracket($tournament, 8);

        $players = Player::take(6)->get();
        $r1Matches = $tournament->matches()->where('bracket_type', 'winners')->where('round', 1)->orderBy('match_number')->get();

        // 6 hráčů ve 4 zápasech:
        // M1: P0 vs P1
        // M2: P2 vs P3
        // M3: P4 vs BYE
        // M4: P5 vs BYE
        $r1Matches[0]->update(['player1_id' => $players[0]->id, 'player2_id' => $players[1]->id]);
        $r1Matches[1]->update(['player1_id' => $players[2]->id, 'player2_id' => $players[3]->id]);
        $r1Matches[2]->update(['player1_id' => $players[4]->id, 'player2_id' => null]);
        $r1Matches[3]->update(['player1_id' => $players[5]->id, 'player2_id' => null]);

        $this->postJson(route('tournaments.finalizeSeeding', $tournament->id))->assertStatus(200);
        $tournament->refresh();
        $this->assertTrue($tournament->is_seeding_finalized);

        // M3 a M4 automaticky postoupili do Semifinále
        $semiMatches = $tournament->matches()->where('bracket_type', 'winners')->where('round', 2)->orderBy('match_number')->get();
        $this->assertEquals($players[4]->id, $semiMatches[1]->fresh()->player1_id);
        $this->assertEquals($players[5]->id, $semiMatches[1]->fresh()->player2_id);

        // 1. Odehrajeme Čtvrtfinále:
        // M1: P0 porazí P1 (2:0) -> P1 vypadává (5.–6. místo)
        $this->put(route('matches.update', $r1Matches[0]->id), [
            'player1_legs' => 2,
            'player2_legs' => 0,
            'status' => 'completed',
        ]);
        // M2: P2 porazí P3 (2:1) -> P3 vypadává (5.–6. místo)
        $this->put(route('matches.update', $r1Matches[1]->id), [
            'player1_legs' => 2,
            'player2_legs' => 1,
            'status' => 'completed',
        ]);

        // 2. Semifinále:
        // SF1 je nyní P0 vs P2
        $this->assertEquals($players[0]->id, $semiMatches[0]->fresh()->player1_id);
        $this->assertEquals($players[2]->id, $semiMatches[0]->fresh()->player2_id);

        // SF1: P0 porazí P2 (2:0) -> P0 jde do Finále, P2 propadá do zápasu o 3. místo (slot 1)!
        $this->put(route('matches.update', $semiMatches[0]->id), [
            'player1_legs' => 2,
            'player2_legs' => 0,
            'status' => 'completed',
        ]);

        // SF2: P4 porazí P5 (2:1) -> P4 jde do Finále, P5 propadá do zápasu o 3. místo (slot 2)!
        $this->put(route('matches.update', $semiMatches[1]->id), [
            'player1_legs' => 2,
            'player2_legs' => 1,
            'status' => 'completed',
        ]);

        // Zkontrolujeme zápas o 3. místo: má P2 (slot 1) a P5 (slot 2)!
        $tpMatch = $tournament->thirdPlaceMatch->fresh();
        $this->assertEquals($players[2]->id, $tpMatch->player1_id);
        $this->assertEquals($players[5]->id, $tpMatch->player2_id);
        $this->assertEquals('scheduled', $tpMatch->status);

        // Finále má P0 vs P4
        $finalMatch = $tournament->matches()->where('bracket_type', 'winners')->where('round', 3)->first();
        $this->assertEquals($players[0]->id, $finalMatch->player1_id);
        $this->assertEquals($players[4]->id, $finalMatch->player2_id);

        // 3. Odehrajeme zápas o 3. místo: P2 porazí P5 (2:1) -> P2 končí 3., P5 končí 4.
        $this->put(route('matches.update', $tpMatch->id), [
            'player1_legs' => 2,
            'player2_legs' => 1,
            'status' => 'completed',
        ]);

        $tournament->refresh();
        // Turnaj ještě není dokončen, protože finále zbývá
        $this->assertNotEquals('finished', $tournament->status);

        // 4. Odehrajeme finále: P0 porazí P4 (2:0) -> P0 je 1., P4 je 2.
        $this->put(route('matches.update', $finalMatch->id), [
            'player1_legs' => 2,
            'player2_legs' => 0,
            'status' => 'completed',
        ]);

        $tournament->refresh();
        $this->assertEquals('finished', $tournament->status);
        $this->assertEquals($players[0]->id, $tournament->winner_id);
        $this->assertEquals($players[4]->id, $tournament->second_place_id);
        $this->assertEquals($players[2]->id, $tournament->third_place_id);

        // Standings kontrola
        $standings = (new \App\Services\TournamentService())->calculateStandings($tournament);
        $this->assertEquals('1.', $standings[0]['rank']);
        $this->assertEquals($players[0]->name, $standings[0]['player_name']);

        $this->assertEquals('2.', $standings[1]['rank']);
        $this->assertEquals($players[4]->name, $standings[1]['player_name']);

        $this->assertEquals('3.', $standings[2]['rank']);
        $this->assertEquals($players[2]->name, $standings[2]['player_name']);

        $this->assertEquals('4.', $standings[3]['rank']);
        $this->assertEquals($players[5]->name, $standings[3]['player_name']);

        $this->assertEquals('5.–6.', $standings[4]['rank']);
        $this->assertEquals('5.–6.', $standings[5]['rank']);

        // UI stránka turnaje zobrazuje sekci pro zápas o 3. a 4. místo
        $playPage = $this->get(route('tournaments.play', $tournament->id));
        $playPage->assertStatus(200);
        $playPage->assertSee('Zápas o 3. a 4. místo');
        $playPage->assertSee('Konečné výsledky turnaje');
    }

    public function test_free_double_elimination_creation_generates_complete_skeleton(): void
    {
        $response = $this->post('/tournaments', [
            'name' => 'Free Double 8 Skeleton Test',
            'format' => 'free_double_elimination',
            'bracket_size' => 8,
        ]);

        $tournament = Tournament::where('name', 'Free Double 8 Skeleton Test')->first();
        $this->assertNotNull($tournament);
        $response->assertRedirect(route('tournaments.play', $tournament->id));

        $this->assertEquals('free_double_elimination', $tournament->format);
        $this->assertEquals(8, $tournament->bracket_size);
        $this->assertFalse($tournament->is_seeding_finalized);
        $this->assertEquals('in_progress', $tournament->status);

        // Winners bracket: 4 v R1, 2 v R2, 1 v R3 = 7
        $this->assertCount(7, $tournament->winnersMatches);
        // Losers bracket: 2 v R1, 2 v R2, 1 v R3, 1 v R4 = 6
        $this->assertCount(6, $tournament->losersMatches);
        // Grand Finals: GF1 a GF2 (reset) = 2
        $this->assertCount(2, $tournament->grandFinalMatches);

        // Všechny zápasy jsou prázdné a čekají
        foreach ($tournament->matches as $m) {
            $this->assertNull($m->player1_id);
            $this->assertNull($m->player2_id);
            $this->assertEquals('scheduled', $m->status);
        }
    }

    public function test_free_double_elimination_bye_in_wb_cascades_permanent_bye_to_lb(): void
    {
        $tournament = Tournament::create([
            'name' => 'Free Double 5 Players BYE Test',
            'format' => 'free_double_elimination',
            'bracket_size' => 8,
            'is_seeding_finalized' => false,
            'status' => 'in_progress',
        ]);
        (new \App\Services\TournamentService())->generateFreeDoubleBracket($tournament, 8);

        $players = Player::take(5)->get();
        $wbR1 = $tournament->matches()->where('bracket_type', 'winners')->where('round', 1)->orderBy('match_number')->get();

        // 5 hráčů:
        // M1: P0 vs P1
        // M2: P2 vs BYE
        // M3: P3 vs BYE
        // M4: P4 vs BYE
        $wbR1[0]->update(['player1_id' => $players[0]->id, 'player2_id' => $players[1]->id]);
        $wbR1[1]->update(['player1_id' => $players[2]->id, 'player2_id' => null]);
        $wbR1[2]->update(['player1_id' => $players[3]->id, 'player2_id' => null]);
        $wbR1[3]->update(['player1_id' => $players[4]->id, 'player2_id' => null]);

        $this->postJson(route('tournaments.finalizeSeeding', $tournament->id))->assertStatus(200);
        $tournament->refresh();
        $this->assertTrue($tournament->is_seeding_finalized);

        // M2 v WB byl BYE -> P2 postoupil do WB R2, M2 nemá loser_id (žádný poražený nepropadá do LB)
        $wbR1M2 = $wbR1[1]->fresh();
        $this->assertTrue($wbR1M2->is_bye);
        $this->assertEquals('completed', $wbR1M2->status);
        $this->assertNull($wbR1M2->loser_id);

        // Nyní v WB R1 odehrajeme reálný zápas M1: P0 porazí P1 (2:0)
        // P1 propadne do LB R1, zápas 1, slot 1
        $this->put(route('matches.update', $wbR1[0]->id), [
            'player1_legs' => 2,
            'player2_legs' => 0,
            'status' => 'completed',
        ]);

        // V LB R1 je zápas 1 krmen z WB M1 (slot 1) a WB M2 (slot 2).
        // Jelikož WB M2 byl BYE (bez poraženého), slot 2 je permanentní BYE!
        // P1 po propadnutí do LB R1 zápas 1 automaticky postupuje do LB R2 bez čekání na neexistujícího soupeře!
        $lbR1M1 = $tournament->matches()->where('bracket_type', 'losers')->where('round', 1)->where('match_number', 1)->first();
        $this->assertTrue($lbR1M1->is_bye);
        $this->assertEquals('completed', $lbR1M1->status);
        $this->assertEquals($players[1]->id, $lbR1M1->winner_id);

        // A P1 se již nachází v LB R2!
        $lbR2M1 = $tournament->matches()->where('bracket_type', 'losers')->where('round', 2)->where('match_number', 1)->first();
        $this->assertEquals($players[1]->id, $lbR2M1->player1_id);
    }

    public function test_free_double_elimination_grand_final_reset_and_standings(): void
    {
        $tournament = Tournament::create([
            'name' => 'Free Double Full Reset Playthrough',
            'format' => 'free_double_elimination',
            'bracket_size' => 8,
            'is_seeding_finalized' => false,
            'status' => 'in_progress',
        ]);
        (new \App\Services\TournamentService())->generateFreeDoubleBracket($tournament, 8);

        $players = Player::take(5)->get();
        $wbR1 = $tournament->matches()->where('bracket_type', 'winners')->where('round', 1)->orderBy('match_number')->get();

        $wbR1[0]->update(['player1_id' => $players[0]->id, 'player2_id' => $players[1]->id]);
        $wbR1[1]->update(['player1_id' => $players[2]->id, 'player2_id' => null]);
        $wbR1[2]->update(['player1_id' => $players[3]->id, 'player2_id' => null]);
        $wbR1[3]->update(['player1_id' => $players[4]->id, 'player2_id' => null]);

        $this->postJson(route('tournaments.finalizeSeeding', $tournament->id))->assertStatus(200);

        // 1. WB R1 M1: P0 porazí P1 (2:0) -> P1 propadá do LB (a díky permanent BYE postupuje rovnou do LB R2)
        $this->put(route('matches.update', $wbR1[0]->id), [
            'player1_legs' => 2,
            'player2_legs' => 0,
            'status' => 'completed',
        ]);

        // 2. WB Semifinále (R2):
        // SF1: P0 vs P2. P0 vyhraje 2:0 nad P2. P2 padá do LB R2.
        $wbSF1 = $tournament->matches()->where('bracket_type', 'winners')->where('round', 2)->where('match_number', 1)->first();
        $this->put(route('matches.update', $wbSF1->id), [
            'player1_legs' => 2,
            'player2_legs' => 0,
            'status' => 'completed',
        ]);

        // SF2: P3 vs P4. P3 vyhraje 2:0 nad P4. P4 padá do LB R2.
        $wbSF2 = $tournament->matches()->where('bracket_type', 'winners')->where('round', 2)->where('match_number', 2)->first();
        $this->put(route('matches.update', $wbSF2->id), [
            'player1_legs' => 2,
            'player2_legs' => 0,
            'status' => 'completed',
        ]);

        // 3. WB Finále (R3): P0 vs P3. P0 vyhraje 2:0 nad P3 -> P0 postupuje do GF1 (ze slotu A)!
        // P3 padá do Finále LB (Round 4, slot 2).
        $wbFinal = $tournament->matches()->where('bracket_type', 'winners')->where('round', 3)->first();
        $this->put(route('matches.update', $wbFinal->id), [
            'player1_legs' => 2,
            'player2_legs' => 0,
            'status' => 'completed',
        ]);

        // 4. LB Zápasy:
        // LB R2:
        // M1 má P1 (slot 1) vs P4 (slot 2). P1 vyhraje 2:1 nad P4. P4 vypadává (5. místo).
        $lbR2M1 = $tournament->matches()->where('bracket_type', 'losers')->where('round', 2)->where('match_number', 1)->first();
        $this->put(route('matches.update', $lbR2M1->id), [
            'player1_legs' => 2,
            'player2_legs' => 1,
            'status' => 'completed',
        ]);

        // M2 v LB R2: slot 2 má P2. Druhý slot byl BYE, takže P2 postoupil do LB R3!
        // LB Semifinále (R3): P1 vs P2. P1 vyhraje 2:0 nad P2. P2 končí 4.!
        $lbSemi = $tournament->matches()->where('bracket_type', 'losers')->where('round', 3)->first();
        $this->put(route('matches.update', $lbSemi->id), [
            'player1_legs' => 2,
            'player2_legs' => 0,
            'status' => 'completed',
        ]);

        // LB Finále (R4): P1 vs P3. P1 vyhraje 2:0 nad P3!
        // P3 končí přirozeně na 3. místě! P1 postupuje do Grand Finále ze slotu B!
        $lbFinal = $tournament->matches()->where('bracket_type', 'losers')->where('round', 4)->first();
        $this->put(route('matches.update', $lbFinal->id), [
            'player1_legs' => 2,
            'player2_legs' => 0,
            'status' => 'completed',
        ]);

        // 5. Grand Finále 1:
        // P0 (z A) vs P1 (z B)
        $gf1 = $tournament->matches()->where('bracket_type', 'grand_final')->where('is_reset_match', false)->first();
        $this->assertEquals($players[0]->id, $gf1->player1_id);
        $this->assertEquals($players[1]->id, $gf1->player2_id);

        // Hráč z B (P1) vyhraje GF1 (2:1) -> AKTIVUJE SE BRACKET RESET (GF2)!
        $this->put(route('matches.update', $gf1->id), [
            'player1_legs' => 1,
            'player2_legs' => 2,
            'status' => 'completed',
        ]);

        $tournament->refresh();
        $this->assertNotEquals('finished', $tournament->status);

        $gf2 = $tournament->matches()->where('bracket_type', 'grand_final')->where('is_reset_match', true)->first();
        $this->assertEquals('scheduled', $gf2->status);
        $this->assertEquals($players[1]->id, $gf2->player1_id);
        $this->assertEquals($players[0]->id, $gf2->player2_id);

        // 6. Grand Finále 2 (Reset Match): P1 vyhraje 2:0 nad P0 a stává se celkovým vítězem!
        $this->put(route('matches.update', $gf2->id), [
            'player1_legs' => 2,
            'player2_legs' => 0,
            'status' => 'completed',
        ]);

        $tournament->refresh();
        $this->assertEquals('finished', $tournament->status);
        $this->assertEquals($players[1]->id, $tournament->winner_id);
        $this->assertEquals($players[0]->id, $tournament->second_place_id);
        $this->assertEquals($players[3]->id, $tournament->third_place_id);

        // Standings kontrola
        $standings = (new \App\Services\TournamentService())->calculateStandings($tournament);
        $this->assertEquals('1.', $standings[0]['rank']);
        $this->assertEquals($players[1]->name, $standings[0]['player_name']);

        $this->assertEquals('2.', $standings[1]['rank']);
        $this->assertEquals($players[0]->name, $standings[1]['player_name']);

        $this->assertEquals('3.', $standings[2]['rank']);
        $this->assertEquals($players[3]->name, $standings[2]['player_name']);

        $this->assertEquals('4.', $standings[3]['rank']);
        $this->assertEquals($players[2]->name, $standings[3]['player_name']);

        $this->assertEquals('5.', $standings[4]['rank']);
        $this->assertEquals($players[4]->name, $standings[4]['player_name']);
    }

    public function test_tournament_standings_persistence_and_reversibility(): void
    {
        $tournament = Tournament::where('name', 'Prague Darts Open 2026')->first();
        $this->assertNotNull($tournament);
        $this->assertEquals('finished', $tournament->status);

        // Ověříme uložení perzistentních bodů v DB tabulce tournament_standings
        $standings = $tournament->standings()->with('player')->get();
        $this->assertNotEmpty($standings);

        $firstStanding = $standings->firstWhere('rank_from', 1);
        $this->assertNotNull($firstStanding);
        $this->assertEquals(10, $firstStanding->points);

        $secondStanding = $standings->firstWhere('rank_from', 2);
        $this->assertNotNull($secondStanding);
        $this->assertEquals(9, $secondStanding->points);

        $thirdStanding = $standings->firstWhere('rank_from', 3);
        $this->assertNotNull($thirdStanding);
        $this->assertEquals(8, $thirdStanding->points);

        // Zpětná editace zápasu smaže záznamy z tournament_standings (idempotence)
        $gf1 = $tournament->matches()->where('bracket_type', 'grand_final')->first();
        $this->put(route('matches.update', $gf1->id), [
            'player1_legs' => 0,
            'player2_legs' => 0,
            'status' => 'scheduled',
        ]);

        $tournament->refresh();
        $this->assertNotEquals('finished', $tournament->status);
        $this->assertEquals(0, $tournament->standings()->count(), 'Body v tabulce tournament_standings musí být při návratu turnaje do hry smazány.');
    }

    public function test_leaderboard_page_loads_and_displays_players_and_sidebar_link(): void
    {
        $response = $this->get(route('leaderboard'));
        $response->assertStatus(200);
        $response->assertSee('Dlouhodobý žebříček hráčů');
        $response->assertSee('Pořadí v žebříčku');
        $response->assertSee('Celkový počet bodů');
        $response->assertSee('Odehrané turnaje');
        $response->assertSee('Žebříček');

        // Ověříme, že v navigaci/sidebaru je odkaz na žebříček
        $this->get(route('dashboard'))
            ->assertStatus(200)
            ->assertSee(route('leaderboard'))
            ->assertSee('Žebříček');
    }

    public function test_tournament_creation_with_ranking_and_season_year(): void
    {
        $players = Player::take(8)->pluck('id')->toArray();

        $response = $this->post(route('tournaments.store'), [
            'name' => 'Exhibition Darts Masters 2025',
            'format' => 'double_elimination',
            'season_year' => 2025,
            'counts_for_ranking' => 0,
            'player_ids' => $players,
        ]);

        $response->assertRedirect();

        $tournament = Tournament::where('name', 'Exhibition Darts Masters 2025')->first();
        $this->assertNotNull($tournament);
        $this->assertEquals(2025, $tournament->season_year);
        $this->assertFalse($tournament->counts_for_ranking);
    }

    public function test_tournament_edit_and_update(): void
    {
        $tournament = Tournament::where('name', 'Prague Darts Open 2026')->first();
        $this->assertNotNull($tournament);

        $editResponse = $this->get(route('tournaments.edit', $tournament->id));
        $editResponse->assertStatus(200);
        $editResponse->assertSee('Nastavení turnaje');
        $editResponse->assertSee('Započítávat do ročníkového žebříčku');

        $updateResponse = $this->put(route('tournaments.update', $tournament->id), [
            'name' => 'Prague Darts Open 2026 Updated',
            'date' => '2026-05-15',
            'season_year' => 2026,
            'counts_for_ranking' => 1,
            'best_of_legs' => 7,
        ]);

        $updateResponse->assertRedirect(route('tournaments.show', $tournament->id));

        $tournament->refresh();
        $this->assertEquals('Prague Darts Open 2026 Updated', $tournament->name);
        $this->assertEquals(7, $tournament->best_of_legs);
        $this->assertTrue($tournament->counts_for_ranking);
        $this->assertEquals(2026, $tournament->season_year);
    }

    public function test_leaderboard_filtering_by_season_and_all_time(): void
    {
        // 1. Current year leaderboard
        $responseCurrent = $this->get(route('leaderboard', ['season' => 2026]));
        $responseCurrent->assertStatus(200);
        $responseCurrent->assertSee('Pořadí šipkařů Resulto');
        $responseCurrent->assertSee('Luke Littler');

        // 2. All-Time leaderboard
        $responseAllTime = $this->get(route('leaderboard', ['season' => 'all_time']));
        $responseAllTime->assertStatus(200);
        $responseAllTime->assertSee('Celkové pořadí šipkařů Resulto — All-Time');
        $responseAllTime->assertSee('Luke Littler');

        // 3. Year without tournaments displays friendly empty state
        $responseEmpty = $this->get(route('leaderboard', ['season' => 2020]));
        $responseEmpty->assertStatus(200);
        $responseEmpty->assertSee('V ročníku 2020 zatím nebyly odehrány žádné turnaje započítávané do žebříčku.');
    }

    public function test_exhibition_tournament_is_excluded_from_leaderboard_and_player_points(): void
    {
        $players = Player::take(2)->get();
        $p1 = $players[0];

        $pointsBefore = $p1->total_points;

        // Vytvoříme exhibiční turnaj v ročníku 2026
        $exhibition = Tournament::create([
            'name' => 'Charity Dart Match 2026',
            'date' => now()->toDateString(),
            'status' => 'in_progress',
            'format' => 'free_oneway_elimination',
            'bracket_size' => 8,
            'counts_for_ranking' => false,
            'season_year' => 2026,
            'is_seeding_finalized' => true,
        ]);

        // Uložíme fiktivní výsledek na 1. místo pro p1
        \App\Models\TournamentStanding::create([
            'tournament_id' => $exhibition->id,
            'player_id' => $p1->id,
            'rank_from' => 1,
            'rank_to' => 1,
            'rank_label' => '1.',
            'points' => 100,
        ]);

        $exhibition->update(['status' => 'finished']);

        // Hráčovo total_points accessor nesmí obsahovat body z exhibice
        $p1->refresh();
        $this->assertEquals($pointsBefore, $p1->total_points);

        // Leaderboard pro ročník 2026 nesmí zahrnovat 100 bodů z exhibice
        $response = $this->get(route('leaderboard', ['season' => 2026]));
        $response->assertStatus(200);
        $this->assertStringNotContainsString((string)($pointsBefore + 100) . ' b.', $response->getContent());
    }

    public function test_bracket_view_contains_no_overflow_y_and_has_responsive_styles(): void
    {
        $tournament = Tournament::where('name', 'Prague Darts Open 2026')->first();
        $response = $this->get(route('tournaments.show', $tournament->id));
        $response->assertStatus(200);
        $response->assertSee('bracket-scroll-wrapper');
        $response->assertSee('bracket-container');
        $response->assertSee('overflow-y: visible !important;', false);
        $response->assertSee('scroll-snap-type: x mandatory;', false);
        $response->assertSee('min-height: 42px;', false);
    }

    public function test_leaderboard_has_no_duplicate_players_and_aggregates_multiple_tournaments(): void
    {
        $player = Player::where('name', 'Luke Littler')->first();
        $this->assertNotNull($player);

        // Vytvoříme druhý dokončený turnaj, ve kterém Luke Littler také získal body
        $secondTournament = Tournament::create([
            'name' => 'Autumn Darts Grand Prix 2026',
            'date' => now()->toDateString(),
            'status' => 'completed',
            'format' => 'free_oneway_elimination',
            'bracket_size' => 8,
            'counts_for_ranking' => true,
            'season_year' => 2026,
            'is_seeding_finalized' => true,
        ]);

        \App\Models\TournamentStanding::create([
            'tournament_id' => $secondTournament->id,
            'player_id' => $player->id,
            'rank_from' => 1,
            'rank_to' => 1,
            'rank_label' => '1.',
            'points' => 10,
        ]);

        $response = $this->get(route('leaderboard', ['season' => 2026]));
        $response->assertStatus(200);

        /** @var \Illuminate\Support\Collection $leaderboard */
        $leaderboard = $response->viewData('players');

        // Ověříme, že každý hráč je v žebříčku zastoupen maximálně jednou
        $playerIds = $leaderboard->pluck('id')->all();
        $playerNames = $leaderboard->pluck('name')->all();

        $this->assertCount(count(array_unique($playerIds)), $playerIds, 'V žebříčku se nesmí vyskytovat duplicitní ID hráčů.');
        $this->assertCount(count(array_unique($playerNames)), $playerNames, 'V žebříčku se nesmí vyskytovat duplicitní jména hráčů.');

        // Ověříme, že Luke Littler má sečtené body z obou turnajů (10 + 10 = 20) a odehrané 2 turnaje
        $lukeInLeaderboard = $leaderboard->firstWhere('name', 'Luke Littler');
        $this->assertNotNull($lukeInLeaderboard);
        $this->assertEquals(20, $lukeInLeaderboard->total_points, 'Body z více turnajů se musí sčítat.');
        $this->assertEquals(2, $lukeInLeaderboard->tournaments_played, 'Počet odehraných turnajů musí být 2.');
    }

    public function test_database_seeder_is_idempotent_and_does_not_duplicate_players(): void
    {
        $countBefore = Player::count();

        // Spustíme seeder podruhé
        $this->seed(\Database\Seeders\DatabaseSeeder::class);

        $countAfter = Player::count();
        $this->assertEquals($countBefore, $countAfter, 'DatabaseSeeder nesmí při opakovaném spuštění duplikovat hráče.');
    }
}
