<?php

namespace App\Services;

use App\Models\Player;
use App\Models\Tournament;
use App\Models\TournamentGroup;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class TournamentGroupService
{
    /**
     * Generate dynamic letter code for group (0 => A, 25 => Z, 26 => AA, 27 => AB, etc.).
     */
    public function getGroupLetter(int $index): string
    {
        $letters = '';
        $index += 1; // 1-based index for Excel-like column lettering

        while ($index > 0) {
            $remainder = ($index - 1) % 26;
            $letters = chr(65 + $remainder) . $letters;
            $index = intdiv($index - 1, 26);
        }

        return $letters;
    }

    /**
     * Get formatted group name (e.g. "Skupina A", "Skupina B", "Skupina AA").
     */
    public function getGroupName(int $index): string
    {
        return 'Skupina ' . $this->getGroupLetter($index);
    }

    /**
     * Calculate balanced distribution of player counts across groups.
     * E.g. 10 players into 3 groups => [4, 3, 3].
     *
     * @return array<int>
     */
    public function calculateDistribution(int $totalPlayers, int $numberOfGroups): array
    {
        if ($numberOfGroups < 1) {
            throw new InvalidArgumentException('Počet skupin musí být alespoň 1.');
        }

        if ($totalPlayers < 2) {
            throw new InvalidArgumentException('Pro rozlosování do skupin je potřeba alespoň 2 hráči.');
        }

        if ($numberOfGroups > $totalPlayers) {
            throw new InvalidArgumentException('Počet skupin nemůže být vyšší než počet přihlášených hráčů.');
        }

        if ($totalPlayers < ($numberOfGroups * 2)) {
            throw new InvalidArgumentException("Každá skupina musí obsahovat minimálně 2 hráče. Pro {$numberOfGroups} skupin je potřeba alespoň " . ($numberOfGroups * 2) . " hráčů.");
        }

        $base = intdiv($totalPlayers, $numberOfGroups);
        $remainder = $totalPlayers % $numberOfGroups;

        $distribution = [];
        for ($i = 0; $i < $numberOfGroups; $i++) {
            $distribution[] = $base + ($i < $remainder ? 1 : 0);
        }

        return $distribution;
    }

    /**
     * Randomly draw and balance players into groups.
     *
     * @param Collection|array $players List of Player models, player IDs, or player arrays
     * @param int $numberOfGroups Number of groups to create
     * @param bool $shuffle Whether to randomize players (Fisher-Yates/shuffle)
     * @return array
     */
    public function generateGroups(Collection|array $players, int $numberOfGroups, bool $shuffle = true): array
    {
        // 1. Normalize player list
        $normalizedPlayers = $this->normalizePlayers($players);
        $totalPlayers = count($normalizedPlayers);

        // 2. Validate and calculate group sizes
        $distribution = $this->calculateDistribution($totalPlayers, $numberOfGroups);

        // 3. Shuffle players (Fisher-Yates / shuffle) if enabled
        if ($shuffle) {
            $this->fisherYatesShuffle($normalizedPlayers);
        }

        // 4. Distribute into balanced groups
        $groups = [];
        $offset = 0;

        for ($i = 0; $i < $numberOfGroups; $i++) {
            $size = $distribution[$i];
            $groupPlayers = array_slice($normalizedPlayers, $offset, $size);
            $offset += $size;

            $groups[] = [
                'order' => $i + 1,
                'letter' => $this->getGroupLetter($i),
                'name' => $this->getGroupName($i),
                'identifier' => 'group_' . $i,
                'target_size' => $size,
                'players_count' => count($groupPlayers),
                'players' => array_values($groupPlayers),
            ];
        }

        return $groups;
    }

    /**
     * Persist tournament groups, player assignments, and generate round-robin matches.
     *
     * @param Tournament $tournament
     * @param array $groupsData Array of groups, each containing 'name' and 'player_ids' (or 'players')
     * @param bool $generateMatches
     * @return Collection<TournamentGroup>
     */
    public function saveTournamentWithGroups(Tournament $tournament, array $groupsData, bool $generateMatches = true): Collection
    {
        return DB::transaction(function () use ($tournament, $groupsData, $generateMatches) {
            // Delete existing groups and group matches if re-saving
            $tournament->groupMatches()->delete();
            $tournament->groups()->delete();

            $createdGroups = collect();
            $allPlayerIds = [];

            foreach ($groupsData as $index => $groupItem) {
                $groupName = $groupItem['name'] ?? $this->getGroupName($index);
                $playerIds = $groupItem['player_ids'] ?? array_column($groupItem['players'] ?? [], 'id');

                if (empty($playerIds) || count($playerIds) < 2) {
                    throw new InvalidArgumentException("Skupina \"{$groupName}\" musí obsahovat minimálně 2 hráče.");
                }

                $group = TournamentGroup::create([
                    'tournament_id' => $tournament->id,
                    'name' => $groupName,
                    'order' => $index + 1,
                    'stage' => 'group_stage',
                ]);

                // Attach players to group with seeds
                $syncData = [];
                foreach (array_values($playerIds) as $seedIdx => $pId) {
                    $syncData[(int) $pId] = [
                        'seed' => $seedIdx + 1,
                        'points' => 0,
                        'matches_played' => 0,
                        'matches_won' => 0,
                        'matches_lost' => 0,
                        'legs_won' => 0,
                        'legs_lost' => 0,
                    ];
                    $allPlayerIds[] = (int) $pId;
                }

                $group->players()->attach($syncData);

                if ($generateMatches) {
                    $group->generateRoundRobinMatches($tournament->best_of_legs ?? 3);
                }

                $createdGroups->push($group);
            }

            // Sync all unique players to tournament_players
            $tournament->players()->sync(array_unique($allPlayerIds));

            return $createdGroups;
        });
    }

    /**
     * Validate manual or submitted group structure.
     *
     * @param array $groups
     * @return array
     */
    public function validateGroupConfiguration(array $groups): array
    {
        if (empty($groups)) {
            throw new InvalidArgumentException('Musí být zadána alespoň jedna skupina.');
        }

        $seenPlayerIds = [];

        foreach ($groups as $idx => $g) {
            $name = $g['name'] ?? ('Skupina ' . ($idx + 1));
            $playerIds = $g['player_ids'] ?? array_column($g['players'] ?? [], 'id');

            if (count($playerIds) < 2) {
                throw new InvalidArgumentException("Skupina \"{$name}\" má méně než 2 hráče. Každá skupina musí obsahovat minimálně 2 hráče.");
            }

            foreach ($playerIds as $pid) {
                if (in_array($pid, $seenPlayerIds, true)) {
                    throw new InvalidArgumentException("Hráč s ID {$pid} je přiřazen do více skupin současně.");
                }
                $seenPlayerIds[] = $pid;
            }
        }

        return $groups;
    }

    /**
     * Normalize player items into uniform array format.
     */
    protected function normalizePlayers(Collection|array $players): array
    {
        $collection = $players instanceof Collection ? $players : collect($players);

        if ($collection->isEmpty()) {
            return [];
        }

        // If collection contains integer/string IDs
        $first = $collection->first();
        if (is_numeric($first)) {
            $playerModels = Player::whereIn('id', $collection->map(fn($v) => (int)$v)->all())
                ->get()
                ->keyBy('id');

            $normalized = [];
            $seenIds = [];

            foreach ($collection as $id) {
                $intId = (int) $id;
                if (in_array($intId, $seenIds, true)) {
                    throw new InvalidArgumentException('Seznam hráčů obsahuje duplicity.');
                }
                $seenIds[] = $intId;

                $model = $playerModels->get($intId);
                if ($model) {
                    $normalized[] = [
                        'id' => $model->id,
                        'name' => $model->name,
                        'gender' => $model->gender,
                        'gender_label' => $model->gender_label,
                    ];
                }
            }

            return $normalized;
        }

        // If collection contains Player models or associative arrays
        $normalized = [];
        $seenIds = [];

        foreach ($collection as $item) {
            if ($item instanceof Player) {
                $id = $item->id;
                $name = $item->name;
                $gender = $item->gender;
                $genderLabel = $item->gender_label;
            } elseif (is_array($item)) {
                $id = $item['id'] ?? null;
                $name = $item['name'] ?? 'Hráč ' . $id;
                $gender = $item['gender'] ?? 'male';
                $genderLabel = $item['gender_label'] ?? ($gender === 'female' ? 'Žena' : 'Muž');
            } else {
                continue;
            }

            if ($id !== null) {
                if (in_array($id, $seenIds, true)) {
                    throw new InvalidArgumentException('Seznam hráčů obsahuje duplicity.');
                }
                $seenIds[] = $id;
            }

            $normalized[] = [
                'id' => $id,
                'name' => $name,
                'gender' => $gender,
                'gender_label' => $genderLabel,
            ];
        }

        return $normalized;
    }

    /**
     * Standard Fisher-Yates shuffle algorithm on an array.
     */
    protected function fisherYatesShuffle(array &$items): void
    {
        $count = count($items);
        for ($i = $count - 1; $i > 0; $i--) {
            $j = random_int(0, $i);
            $temp = $items[$i];
            $items[$i] = $items[$j];
            $items[$j] = $temp;
        }
    }
}
