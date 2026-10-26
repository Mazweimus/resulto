<?php

namespace Tests\Feature;

use App\Models\Player;
use App\Models\Tournament;
use App\Models\TournamentGroup;
use App\Services\TournamentGroupService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

class TournamentGroupTest extends TestCase
{
    use RefreshDatabase;

    protected TournamentGroupService $groupService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        $this->groupService = new TournamentGroupService();
    }

    /**
     * Test rozlosování přesně 10 hráčů do 3 skupin s očekávaným poměrem 4, 3, 3.
     */
    public function test_draw_10_players_into_3_groups_results_in_4_3_3_distribution(): void
    {
        // 1. Připravíme přesně 10 unikátních hráčů
        $players = Player::take(10)->get();
        if ($players->count() < 10) {
            for ($i = $players->count(); $i < 10; $i++) {
                Player::create(['name' => 'Test Player ' . ($i + 1), 'gender' => 'male']);
            }
            $players = Player::take(10)->get();
        }

        $this->assertCount(10, $players);

        // 2. Provedeme rozlosování přes servisní třídu
        $groups = $this->groupService->generateGroups($players, 3);

        // 3. Ověříme počet skupin
        $this->assertCount(3, $groups);

        // 4. Ověříme názvy a písmena skupin
        $this->assertEquals('Skupina A', $groups[0]['name']);
        $this->assertEquals('A', $groups[0]['letter']);

        $this->assertEquals('Skupina B', $groups[1]['name']);
        $this->assertEquals('B', $groups[1]['letter']);

        $this->assertEquals('Skupina C', $groups[2]['name']);
        $this->assertEquals('C', $groups[2]['letter']);

        // 5. Ověříme balanční logiku velikostí: 4, 3, 3
        $this->assertCount(4, $groups[0]['players'], 'První skupina A musí mít 4 hráče (3 + 1 zbytek).');
        $this->assertCount(3, $groups[1]['players'], 'Druhá skupina B musí mít 3 hráče.');
        $this->assertCount(3, $groups[2]['players'], 'Třetí skupina C musí mít 3 hráče.');

        // 6. Ověříme, že celkem je v distribuci přesně 10 hráčů bez duplicit
        $assignedIds = [];
        foreach ($groups as $group) {
            foreach ($group['players'] as $p) {
                $assignedIds[] = $p['id'];
            }
        }

        $this->assertCount(10, $assignedIds);
        $this->assertCount(10, array_unique($assignedIds), 'Každý hráč musí být zařazen právě v jedné skupině.');
    }

    /**
     * Test výpočtu distribuce pro různé počty hráčů a skupin.
     */
    public function test_calculate_distribution_various_scenarios(): void
    {
        // 10 hráčů do 3 skupin => [4, 3, 3]
        $this->assertEquals([4, 3, 3], $this->groupService->calculateDistribution(10, 3));

        // 14 hráčů do 4 skupin => [4, 4, 3, 3]
        $this->assertEquals([4, 4, 3, 3], $this->groupService->calculateDistribution(14, 4));

        // 8 hráčů do 2 skupin => [4, 4]
        $this->assertEquals([4, 4], $this->groupService->calculateDistribution(8, 2));

        // 9 hráčů do 3 skupin => [3, 3, 3]
        $this->assertEquals([3, 3, 3], $this->groupService->calculateDistribution(9, 3));

        // 11 hráčů do 3 skupin => [4, 4, 3]
        $this->assertEquals([4, 4, 3], $this->groupService->calculateDistribution(11, 3));
    }

    /**
     * Test dynamického generování písmen skupin (A–Z, AA, AB, BA apod.).
     */
    public function test_dynamic_group_lettering_past_z(): void
    {
        $this->assertEquals('A', $this->groupService->getGroupLetter(0));
        $this->assertEquals('B', $this->groupService->getGroupLetter(1));
        $this->assertEquals('Z', $this->groupService->getGroupLetter(25));
        $this->assertEquals('AA', $this->groupService->getGroupLetter(26));
        $this->assertEquals('AB', $this->groupService->getGroupLetter(27));
        $this->assertEquals('AZ', $this->groupService->getGroupLetter(51));
        $this->assertEquals('BA', $this->groupService->getGroupLetter(52));

        $this->assertEquals('Skupina AA', $this->groupService->getGroupName(26));
    }

    /**
     * Test edge case: počet skupin je větší než počet hráčů.
     */
    public function test_exception_when_groups_count_exceeds_players_count(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Počet skupin nemůže být vyšší než počet přihlášených hráčů.');

        $players = Player::take(3)->get();
        $this->groupService->generateGroups($players, 4);
    }

    /**
     * Test edge case: méně než 2 hráči na skupinu.
     */
    public function test_exception_when_less_than_two_players_per_group(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Každá skupina musí obsahovat minimálně 2 hráče.');

        // 5 hráčů do 3 skupin by vedlo k rozdělení [2, 2, 1] - skupina C má jen 1 hráče
        $players = Player::take(5)->get();
        $this->groupService->generateGroups($players, 3);
    }

    /**
     * Test edge case: duplicitní ID hráčů vyvolá výjimku.
     */
    public function test_exception_when_duplicate_player_ids_provided(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Seznam hráčů obsahuje duplicity.');

        $duplicateIds = [1, 2, 3, 2, 4, 5];
        $this->groupService->generateGroups($duplicateIds, 2);
    }

    /**
     * Test controller endpointu pro náhled rozlosování (POST /tournaments/groups/draw).
     */
    public function test_controller_draw_preview_endpoint_returns_json_with_balanced_groups(): void
    {
        $players = Player::take(10)->pluck('id')->toArray();

        $response = $this->postJson(route('tournaments.groups.draw'), [
            'player_ids' => $players,
            'groups_count' => 3,
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'groups_count' => 3,
            'total_players' => 10,
        ]);

        $groups = $response->json('groups');
        $this->assertCount(3, $groups);
        $this->assertCount(4, $groups[0]['players']);
        $this->assertCount(3, $groups[1]['players']);
        $this->assertCount(3, $groups[2]['players']);
    }

    /**
     * Test validace v DrawGroupsRequest.
     */
    public function test_draw_groups_request_validation(): void
    {
        // 1. Chybějící pole
        $this->postJson(route('tournaments.groups.draw'), [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['player_ids', 'groups_count']);

        // 2. Nedostatek hráčů pro 3 skupiny (potřeba min 6, zadáno 4)
        $this->postJson(route('tournaments.groups.draw'), [
            'player_ids' => [1, 2, 3, 4],
            'groups_count' => 3,
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['groups_count']);
    }

    /**
     * Test uložení turnaje se skupinami a ověření v databázi včetně vygenerování zápasů.
     */
    public function test_store_tournament_with_groups_and_round_robin_matches(): void
    {
        $players = Player::take(10)->get();
        $pIds = $players->pluck('id')->all();

        // 10 hráčů rozdělených po manuální úpravě (např. 4 ve Skupině A, 3 ve Skupině B, 3 ve Skupině C)
        $payload = [
            'name' => 'Mistrovství ve skupinách 2026',
            'date' => '2026-11-15',
            'season_year' => 2026,
            'counts_for_ranking' => 1,
            'best_of_legs' => 5,
            'game_mode' => '501',
            'checkout_mode' => 'double_out',
            'groups' => [
                [
                    'name' => 'Skupina A',
                    'player_ids' => [$pIds[0], $pIds[1], $pIds[2], $pIds[3]],
                ],
                [
                    'name' => 'Skupina B',
                    'player_ids' => [$pIds[4], $pIds[5], $pIds[6]],
                ],
                [
                    'name' => 'Skupina C',
                    'player_ids' => [$pIds[7], $pIds[8], $pIds[9]],
                ],
            ],
        ];

        $response = $this->post(route('tournaments.groups.store'), $payload);
        $response->assertRedirect();

        // 1. Ověření vytvoření turnaje
        $tournament = Tournament::where('name', 'Mistrovství ve skupinách 2026')->first();
        $this->assertNotNull($tournament);
        $this->assertEquals('groups', $tournament->format);
        $this->assertEquals(2026, $tournament->season_year);
        $this->assertTrue($tournament->counts_for_ranking);

        // 2. Ověření vytvoření 3 skupin v DB
        $groups = $tournament->groups()->orderBy('order')->get();
        $this->assertCount(3, $groups);
        $this->assertEquals('Skupina A', $groups[0]->name);
        $this->assertEquals('Skupina B', $groups[1]->name);
        $this->assertEquals('Skupina C', $groups[2]->name);

        // 3. Ověření hráčů v jednotlivých skupinách
        $this->assertCount(4, $groups[0]->players);
        $this->assertCount(3, $groups[1]->players);
        $this->assertCount(3, $groups[2]->players);

        // 4. Ověření vygenerování zápasů (Round Robin)
        // Pro 4 hráče: 4*3/2 = 6 zápasů
        $this->assertEquals(6, $groups[0]->matches()->count());

        // Pro 3 hráče: 3*2/2 = 3 zápasy
        $this->assertEquals(3, $groups[1]->matches()->count());
        $this->assertEquals(3, $groups[2]->matches()->count());

        // Celkem 6 + 3 + 3 = 12 zápasů
        $this->assertEquals(12, $tournament->groupMatches()->count());
    }

    /**
     * Test zobrazení stránky skupinového turnaje (GET /tournaments/{tournament}/groups).
     */
    public function test_group_tournament_show_page_loads_with_tables_and_matches(): void
    {
        $players = Player::take(6)->pluck('id')->all();

        $tournament = Tournament::create([
            'name' => 'Podzimní liga skupin',
            'date' => now()->toDateString(),
            'status' => 'ongoing',
            'format' => 'groups',
            'best_of_legs' => 3,
            'counts_for_ranking' => true,
            'season_year' => 2026,
        ]);

        $this->groupService->saveTournamentWithGroups($tournament, [
            [
                'name' => 'Skupina A',
                'player_ids' => [$players[0], $players[1], $players[2]],
            ],
            [
                'name' => 'Skupina B',
                'player_ids' => [$players[3], $players[4], $players[5]],
            ],
        ]);

        $response = $this->get(route('tournaments.groups.show', $tournament->id));
        $response->assertStatus(200);
        $response->assertSee('Podzimní liga skupin');
        $response->assertSee('Skupina A');
        $response->assertSee('Skupina B');
        $response->assertSee('Tabulka: Skupina A');
        $response->assertSee('Křížová tabulka zápasů: Skupina A');
        $response->assertSee('diagonal-cell');
    }

    /**
     * Test inline uložení turnaje se skupinami přes hlavní endpoint POST /tournaments.
     */
    public function test_inline_store_tournament_with_groups_via_tournaments_store(): void
    {
        $players = Player::take(6)->pluck('id')->all();

        $payload = [
            'name' => 'Turnaj se skupinami inline',
            'format' => 'groups',
            'season_year' => 2026,
            'counts_for_ranking' => 1,
            'best_of_legs' => 3,
            'groups' => [
                [
                    'name' => 'Skupina A',
                    'player_ids' => [$players[0], $players[1], $players[2]],
                ],
                [
                    'name' => 'Skupina B',
                    'player_ids' => [$players[3], $players[4], $players[5]],
                ],
            ],
        ];

        $response = $this->post(route('tournaments.store'), $payload);
        $response->assertRedirect();

        $tournament = Tournament::where('name', 'Turnaj se skupinami inline')->first();
        $this->assertNotNull($tournament);
        $this->assertEquals('groups', $tournament->format);
        $this->assertEquals(2, $tournament->groups()->count());
        $this->assertEquals(6, $tournament->groupMatches()->count());
    }

    /**
     * Test automatického rozlosování skupin v POST /tournaments, pokud jsou poslána pouze player_ids a groups_count.
     */
    public function test_inline_store_tournament_auto_draws_when_only_player_ids_and_groups_count_sent(): void
    {
        $players = Player::take(8)->pluck('id')->all();

        $payload = [
            'name' => 'Auto Draw Skupinový Turnaj',
            'format' => 'groups',
            'groups_count' => 2,
            'player_ids' => $players,
            'season_year' => 2026,
            'counts_for_ranking' => 1,
        ];

        $response = $this->post(route('tournaments.store'), $payload);
        $response->assertRedirect();

        $tournament = Tournament::where('name', 'Auto Draw Skupinový Turnaj')->first();
        $this->assertNotNull($tournament);
        $this->assertEquals('groups', $tournament->format);
        $this->assertEquals(2, $tournament->groups()->count());

        $gA = $tournament->groups()->where('name', 'Skupina A')->first();
        $gB = $tournament->groups()->where('name', 'Skupina B')->first();
        $this->assertCount(4, $gA->players);
        $this->assertCount(4, $gB->players);
    }

    /**
     * Test, že skupinový zápas povoluje remízu a endpoint vrací JSON odpověď pro AJAX.
     */
    public function test_group_match_score_update_allows_draw_and_returns_json(): void
    {
        $players = Player::take(4)->pluck('id')->all();

        $tournament = Tournament::create([
            'name' => 'Test Remíz Skupina',
            'format' => 'groups',
            'status' => 'ongoing',
            'best_of_legs' => 4,
            'counts_for_ranking' => true,
            'season_year' => 2026,
        ]);

        $this->groupService->saveTournamentWithGroups($tournament, [
            [
                'name' => 'Skupina A',
                'player_ids' => [$players[0], $players[1]],
            ],
            [
                'name' => 'Skupina B',
                'player_ids' => [$players[2], $players[3]],
            ],
        ]);

        $match = $tournament->groupMatches()->first();
        $this->assertNotNull($match);

        // Uložení remízy 2:2 přes AJAX JSON PUT /matches/{match}
        $response = $this->putJson(route('matches.update', $match->id), [
            'player1_legs' => 2,
            'player2_legs' => 2,
            'status' => 'completed',
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'match' => [
                'id' => $match->id,
                'player1_legs' => 2,
                'player2_legs' => 2,
                'winner_id' => null,
                'loser_id' => null,
                'status' => 'completed',
            ],
        ]);

        $match->refresh();
        $this->assertEquals(2, $match->player1_legs);
        $this->assertEquals(2, $match->player2_legs);
        $this->assertEquals('completed', $match->status);
        $this->assertNull($match->winner_id);
        $this->assertNull($match->loser_id);
    }

    /**
     * Test, že vyřazovací zápas (Double / Single Elimination) stále odmítá remízu.
     */
    public function test_elimination_match_still_rejects_draw(): void
    {
        $players = Player::take(2)->pluck('id')->all();

        $tournament = Tournament::create([
            'name' => 'Double Elim Test',
            'format' => 'double_elimination',
            'status' => 'ongoing',
            'best_of_legs' => 3,
            'counts_for_ranking' => true,
            'season_year' => 2026,
        ]);

        $tournament->players()->attach($players);
        $tournament->generateDoubleEliminationMatches($players);

        $match = $tournament->matches()->where('bracket_type', 'winners')->first();
        $this->assertNotNull($match);

        $response = $this->putJson(route('matches.update', $match->id), [
            'player1_legs' => 2,
            'player2_legs' => 2,
            'status' => 'completed',
        ]);

        $response->assertStatus(422);
        $response->assertJson([
            'success' => false,
            'message' => 'Vyřazovací zápas nemůže skončit remízou. Jeden z hráčů musí zvítězit.',
        ]);
    }

    /**
     * Test, že RecalculateGroupStandingsAction správně aplikuje pravidla bodování a šipkové tie-breakery.
     */
    public function test_recalculate_group_standings_action_applies_full_tie_breaker_rules(): void
    {
        $p1 = Player::create(['name' => 'Alice', 'gender' => 'female']);
        $p2 = Player::create(['name' => 'Bob', 'gender' => 'male']);
        $p3 = Player::create(['name' => 'Charlie', 'gender' => 'male']);
        $p4 = Player::create(['name' => 'David', 'gender' => 'male']);

        $tournament = Tournament::create([
            'name' => 'Tie Breaker Test',
            'format' => 'groups',
            'status' => 'ongoing',
            'best_of_legs' => 3,
        ]);

        $this->groupService->saveTournamentWithGroups($tournament, [
            [
                'name' => 'Skupina A',
                'player_ids' => [$p1->id, $p2->id, $p3->id, $p4->id],
            ],
        ]);

        $group = $tournament->groups()->first();

        $updateMatch = function ($playerAId, $playerBId, $aLegs, $bLegs) use ($group) {
            $m = $group->matches()->where(function ($q) use ($playerAId, $playerBId) {
                $q->where('player1_id', $playerAId)->where('player2_id', $playerBId);
            })->orWhere(function ($q) use ($playerAId, $playerBId) {
                $q->where('player1_id', $playerBId)->where('player2_id', $playerAId);
            })->first();

            $p1IsA = ($m->player1_id === $playerAId);
            $p1Legs = $p1IsA ? $aLegs : $bLegs;
            $p2Legs = $p1IsA ? $bLegs : $aLegs;

            $winnerId = null;
            $loserId = null;
            if ($aLegs > $bLegs) {
                $winnerId = $playerAId;
                $loserId = $playerBId;
            } elseif ($bLegs > $aLegs) {
                $winnerId = $playerBId;
                $loserId = $playerAId;
            }

            $m->update([
                'player1_legs' => $p1Legs,
                'player2_legs' => $p2Legs,
                'winner_id' => $winnerId,
                'loser_id' => $loserId,
                'status' => 'completed',
            ]);
        };

        // David vyhraje všechny zápasy 2:0 => 6 bodů (1. místo)
        $updateMatch($p4->id, $p1->id, 2, 0);
        $updateMatch($p4->id, $p2->id, 2, 0);
        $updateMatch($p4->id, $p3->id, 2, 0);

        // Alice vs Bob: Alice vyhrála 2:1
        $updateMatch($p1->id, $p2->id, 2, 1);

        // Alice vs Charlie: Charlie vyhrál 2:1
        $updateMatch($p3->id, $p1->id, 2, 1);

        // Bob vs Charlie: Bob vyhrál 2:1
        $updateMatch($p2->id, $p3->id, 2, 1);

        // Charlie vs David již odehrán (David 2:0)

        // Statistika Alice:
        // vs Bob: 2:1 (W)
        // vs Charlie: 1:2 (L)
        // vs David: 0:2 (L)
        // Body: 2, Legy vyhrané: 3, Legy prohrané: 5, Diff: -2

        // Statistika Boba:
        // vs Alice: 1:2 (L)
        // vs Charlie: 2:1 (W)
        // vs David: 0:2 (L)
        // Body: 2, Legy vyhrané: 3, Legy prohrané: 5, Diff: -2

        // Statistika Charlieho:
        // vs Alice: 2:1 (W)
        // vs Bob: 1:2 (L)
        // vs David: 0:2 (L)
        // Body: 2, Legy vyhrané: 3, Legy prohrané: 5, Diff: -2

        // Alice a Bob mají:
        // Body: 2 == 2
        // Diff: -2 == -2
        // Legy: 3 == 3
        // Vzájemný zápas (H2H): Alice vyhrála nad Bobem 2:1!
        // Alice tedy musí být v pořadí před Bobem!

        $standings = (new \App\Actions\RecalculateGroupStandingsAction())->execute($group);

        // David je 1. (6 bodů)
        $this->assertEquals(1, $standings[0]['rank']);
        $this->assertEquals($p4->id, $standings[0]['player_id']);
        $this->assertEquals(6, $standings[0]['points']);

        // Ověříme, že Alice má 2 body a diff -2
        $aliceStanding = $standings->firstWhere('player_id', $p1->id);
        $bobStanding = $standings->firstWhere('player_id', $p2->id);

        $this->assertEquals(2, $aliceStanding['points']);
        $this->assertEquals(2, $bobStanding['points']);
        $this->assertEquals(-2, $aliceStanding['legs_diff']);
        $this->assertEquals(-2, $bobStanding['legs_diff']);
        $this->assertEquals(3, $aliceStanding['legs_won']);
        $this->assertEquals(3, $bobStanding['legs_won']);

        // H2H Alice vs Bob: Alice vyhrála 2:1, takže její rank musí být lepší (menší číslo) než Bobův
        $this->assertLessThan($bobStanding['rank'], $aliceStanding['rank'], 'Alice musí být před Bobem díky výhře ve vzájemném zápase.');
    }

    /**
     * Test, že zápasy se stavem 'pending' a 'in_progress' se nezapočítávají do pořadí.
     */
    public function test_matches_in_pending_or_in_progress_are_ignored_in_standings(): void
    {
        $players = Player::take(2)->pluck('id')->all();

        $tournament = Tournament::create([
            'name' => 'Pending Test',
            'format' => 'groups',
            'status' => 'ongoing',
        ]);

        $this->groupService->saveTournamentWithGroups($tournament, [
            [
                'name' => 'Skupina A',
                'player_ids' => $players,
            ],
        ]);

        $group = $tournament->groups()->first();
        $match = $group->matches()->first();

        // Nastavíme zápas na in_progress se zapsaným skóre (které se ale nesmí započítat)
        $match->update([
            'player1_legs' => 2,
            'player2_legs' => 0,
            'status' => 'in_progress',
        ]);

        $standings = $group->recalculateStandings();

        // Žádný zápas není 'completed', všichni hráči mají Z=0, V=0, Body=0
        $this->assertEquals(0, $standings[0]['matches_played']);
        $this->assertEquals(0, $standings[0]['points']);
        $this->assertEquals(0, $standings[1]['matches_played']);
        $this->assertEquals(0, $standings[1]['points']);
    }

    /**
     * Test interaktivní změny stavu zápasu přes PUT endpoint.
     */
    public function test_match_status_can_be_updated_interactively_via_put_request(): void
    {
        $players = Player::take(2)->pluck('id')->all();

        $tournament = Tournament::create([
            'name' => 'Status Dropdown Test',
            'format' => 'groups',
            'status' => 'ongoing',
        ]);

        $this->groupService->saveTournamentWithGroups($tournament, [
            [
                'name' => 'Skupina A',
                'player_ids' => $players,
            ],
        ]);

        $match = $tournament->groupMatches()->first();

        // 1. Změna stavu na in_progress
        $response = $this->putJson(route('matches.update', $match->id), [
            'status' => 'in_progress',
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'match' => [
                'id' => $match->id,
                'status' => 'in_progress',
            ],
        ]);

        $match->refresh();
        $this->assertEquals('in_progress', $match->status);

        // 2. Změna stavu zpět na pending
        $response = $this->putJson(route('matches.update', $match->id), [
            'status' => 'pending',
        ]);

        $response->assertStatus(200);
        $match->refresh();
        $this->assertEquals('pending', $match->status);
    }

    /**
     * Test, že na detailu skupinového turnaje nejsou tlačítka "+ Nový turnaj" a "Všechny turnaje" v hlavičce,
     * a jsou zobrazeny medaile a status dropdown.
     */
    public function test_group_detail_view_has_no_redundant_header_buttons_and_has_medals_and_status_dropdown(): void
    {
        $players = Player::take(4)->pluck('id')->all();

        $tournament = Tournament::create([
            'name' => 'UI Test Turnaj',
            'format' => 'groups',
            'status' => 'ongoing',
        ]);

        $this->groupService->saveTournamentWithGroups($tournament, [
            [
                'name' => 'Skupina A',
                'player_ids' => $players,
            ],
        ]);

        $response = $this->get(route('tournaments.groups.show', $tournament->id));
        $response->assertStatus(200);

        // Ověření, že byla odstraněna redundantní tlačítka
        $response->assertDontSee('+ Nový turnaj');
        $response->assertDontSee('Všechny turnaje');

        // Ověření přítomnosti medailových odznaků a dropdownu stavu
        $response->assertSee('medal-badge-gold');
        $response->assertSee('medal-badge-silver');
        $response->assertSee('medal-badge-bronze');
        $response->assertSee('match-status-dropdown');
        $response->assertSee('Připravuje se');
        $response->assertSee('Právě se hraje');
    }
}
