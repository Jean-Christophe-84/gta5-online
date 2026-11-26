<?php

namespace App\Helper;

use App\Entity\HeistKortzCenter;
use App\Enum\HeistKortzCenterDifficulty;
use App\Enum\HeistKortzCenterMaterial;
use App\Enum\HeistKortzCenterVehicle;
use App\Enum\HeistKortzCenterWeapon;
use Doctrine\DBAL\Exception as DoctrineDBALException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;

readonly class HeistKortzCenterHelper
{
    public function __construct(
        private ConfigurationHelper $configurationHelper, private EntityManagerInterface $entityManager, private Security $security
    ) {
    }

    public function buildPossibleLoots(array $loots): array
    {
        $possibleLoots = [];

        foreach ($loots as $loot) {
            $name          = (string)$loot['name'];
            $percentageBag = (float)($loot['percentageBag'] ?? 0);
            $price         = (float)($loot['price'] ?? 0);
            $type          = (string)$loot['type'];

            if ($price <= 0 || $percentageBag <= 0) {
                continue;
            }

            $possibleLoots[] = [
                'area'          => $loot['area'],
                'id'            => $loot['id'],
                'name'          => $name,
                'number'        => 1,
                'percentageBag' => $percentageBag,
                'price'         => $price,
                'type'          => $type,
            ];
        }

        return $possibleLoots;
    }

    public function getDifficulties(): array
    {
        return [
            HeistKortzCenterDifficulty::NORMAL,
            HeistKortzCenterDifficulty::HARD
        ];
    }

    /**
     * @throws DoctrineDBALException
     */
    public function getHeistKortzCenterMainLoots(): array
    {
        $query = "
            SELECT heist_kortz_center_main_loot.id, heist_kortz_center_main_loot_locale.name, heist_kortz_center_main_loot.price
            FROM heist_kortz_center_main_loot
            LEFT JOIN heist_kortz_center_main_loot_locale
                ON heist_kortz_center_main_loot_locale.heist_kortz_center_main_loot_id = heist_kortz_center_main_loot.id
                AND heist_kortz_center_main_loot_locale.locale_id = :localeId
            ORDER BY heist_kortz_center_main_loot_locale.name
        ";

        $statement = $this->entityManager->getConnection()->prepare($query);
        $statement->bindValue('localeId', $this->security->getUser()->getLocale()->id);

        return $statement->executeQuery()->fetchAllAssociative();
    }

    /**
     * @throws DoctrineDBALException
     */
    public function getHeistKortzCenterSecondaryLoots(int $userId, ?bool $active = null, bool $solo = false): array
    {
        $query = "
            SELECT heist_kortz_center_secondary_loot.id,
                   MAX(IF(heist_kortz_center_heist_kortz_center_secondary_loot.user_id IS NOT NULL, heist_kortz_center_secondary_loot_name_locale.name, NULL)) AS name,
                   MAX(heist_kortz_center_heist_kortz_center_secondary_loot.price) AS price, heist_kortz_center_secondary_loot.percentage_bag AS percentageBag,
                   heist_kortz_center_secondary_loot.type, MAX(heist_kortz_center_heist_kortz_center_secondary_loot.active) AS active,
                   MAX(heist_kortz_center_heist_kortz_center_secondary_loot.buyer_request) AS buyerRequest,
                   GROUP_CONCAT(DISTINCT heist_kortz_center_secondary_loot_name.id ORDER BY heist_kortz_center_secondary_loot_name_locale.name ASC SEPARATOR '+') AS heistKortzCenterSecondaryLootNameIds,
                   GROUP_CONCAT(DISTINCT heist_kortz_center_secondary_loot_name_locale.name ORDER BY heist_kortz_center_secondary_loot_name_locale.name ASC SEPARATOR '+') AS heistKortzCenterSecondaryLootNameNames
            FROM heist_kortz_center_secondary_loot
            LEFT JOIN heist_kortz_center_secondary_loot_name
                ON heist_kortz_center_secondary_loot_name.heist_kortz_center_secondary_loot_id = heist_kortz_center_secondary_loot.id
            LEFT JOIN heist_kortz_center_secondary_loot_name_locale
                ON heist_kortz_center_secondary_loot_name_locale.heist_kortz_center_secondary_loot_name_id = heist_kortz_center_secondary_loot_name.id
                AND heist_kortz_center_secondary_loot_name_locale.locale_id = :localeId
            LEFT JOIN heist_kortz_center_heist_kortz_center_secondary_loot
                ON heist_kortz_center_heist_kortz_center_secondary_loot.heist_kortz_center_secondary_loot_name_id = heist_kortz_center_secondary_loot_name.id
                AND heist_kortz_center_heist_kortz_center_secondary_loot.user_id = :userId
        ";

        if ($solo) {
            $query .= " WHERE heist_kortz_center_secondary_loot.id NOT BETWEEN 7 AND 13";
        }

        $query .= " GROUP BY heist_kortz_center_secondary_loot.id";

        if ($active !== null) {
            $query .= " HAVING active = :active";
        }

        $statement = $this->entityManager->getConnection()->prepare($query);
        $statement->bindValue('localeId', $this->security->getUser()->getLocale()->id);
        $statement->bindValue('userId', $userId);

        if ($active !== null) {
            $statement->bindValue('active', $active);
        }

        return $statement->executeQuery()->fetchAllAssociative();
    }

    public function getMaterials(): array
    {
        return [
            HeistKortzCenterMaterial::EMPLOYEE_ACCESS_CARD,
            HeistKortzCenterMaterial::TACTICAL_EQUIPMENT,
            HeistKortzCenterMaterial::ALPHA_MAIL_OUTFIT,
            HeistKortzCenterMaterial::SAFETY_SUIT
        ];
    }

    public function getVehicles(): array
    {
        return [
            HeistKortzCenterVehicle::MANCHEZ,
            HeistKortzCenterVehicle::CARACARA_ARMORED,
            HeistKortzCenterVehicle::ANNIHILATOR_STEALTH
        ];
    }

    public function getWeapons(): array
    {
        return [
            HeistKortzCenterWeapon::STREET_ARSENAL,
            HeistKortzCenterWeapon::SECURITY_ARSENAL,
            HeistKortzCenterWeapon::MILITARY_ARSENAL
        ];
    }

    /**
     * Valeur acceptée pour un butin secondaire : le nom choisi doit appartenir à ce butin
     *
     * @throws DoctrineDBALException
     */
    public function isValidSecondaryLootValue(string $type, ?string $value, int $secondaryLootId): bool
    {
        return match ($type) {
            'active', 'buyer_request'                   => in_array($value, ['0', '1'], true),
            'heist_kortz_center_secondary_loot_name_id' => $value !== null && (bool)$this->entityManager->getConnection()->fetchOne(
                'SELECT 1 FROM heist_kortz_center_secondary_loot_name WHERE id = ? AND heist_kortz_center_secondary_loot_id = ?', [$value, $secondaryLootId]
            ),
            'price'                                     => $value === null || ctype_digit($value),
            default                                     => false
        };
    }

    /**
     * Valeur acceptée pour un choix du braquage (null = choix vidé)
     *
     * @throws DoctrineDBALException
     */
    public function isValidValue(string $place, ?string $value): bool
    {
        if ($value === null) {
            return true;
        }

        return match ($place) {
            'difficulty'         => HeistKortzCenterDifficulty::tryFrom($value) !== null,
            'first_time_in_week' => in_array($value, ['0', '1'], true),
            'main_loot_id'       => (bool)$this->entityManager->getConnection()->fetchOne('SELECT 1 FROM heist_kortz_center_main_loot WHERE id = ?', [$value]),
            'material'           => HeistKortzCenterMaterial::tryFrom($value) !== null,
            'vehicle'            => HeistKortzCenterVehicle::tryFrom($value) !== null,
            'weapon'             => HeistKortzCenterWeapon::tryFrom($value) !== null,
            default              => false
        };
    }

    /**
     * @throws DoctrineDBALException
     */
    public function optimizeProfits(HeistKortzCenter $heistKortzCenter, int $players): array
    {
        $loots = [];

        if ($secondaryLoots = $this->getHeistKortzCenterSecondaryLoots($heistKortzCenter->getUser()->id, true, $players === 1)) {
            foreach ($secondaryLoots as $secondaryLoot) {
                $loots[] = [
                    'area'          => $this->getArea($secondaryLoot['id']),
                    'id'            => $secondaryLoot['id'],
                    'name'          => $secondaryLoot['name'],
                    'percentageBag' => $secondaryLoot['percentageBag'],
                    'price'         => $secondaryLoot['price'],
                    'type'          => $secondaryLoot['type']
                ];
            }
        }

        $possibleLoots = $this->buildPossibleLoots(loots: $loots);
        $bestResult    = $this->optimizeRoad(routeLoots: $possibleLoots, playersCount: $players);

        $kortzCenter = [];

        for ($i = 1; $i <= $players; $i++) {
            $kortzCenter[$i]  = '';
            $kortzCenterParts = [];

            if (!empty($bestResult['distribution'][$i])) {
                usort($bestResult['distribution'][$i], $this->compareDistributionProfitability(...));

                foreach ($bestResult['distribution'][$i] as $distribution) {
                    $kortzCenterParts[] = '<span class="' . htmlspecialchars((string)$distribution['type'], ENT_QUOTES) . '">' . $distribution['id'] . ' - '
                        . htmlspecialchars((string)$distribution['name'], ENT_QUOTES) . ' (' . $this->configurationHelper->formatMoney($distribution['price']) . ')' . ' [' . $this->formatTakenPercentage($distribution['takenPercentageBag']) . ']</span>';
                }
            }

            if ($kortzCenterParts !== []) {
                $kortzCenter[$i] .= implode('<br/>', $kortzCenterParts);
            }
        }

        if ($heistKortzCenter->getDifficulty() == HeistKortzCenterDifficulty::NORMAL) {
            $amountBuyerRequest   = 50000;
            $amountEliteChallenge = 50000;
            $percentDifficulty    = 1;
            $totalSecondary       = 100000;
        } elseif ($heistKortzCenter->getDifficulty() == HeistKortzCenterDifficulty::HARD) {
            $amountBuyerRequest   = 100000;
            $amountEliteChallenge = 100000;
            $percentDifficulty    = 1.1;
            $totalSecondary       = 200000;
        } else {
            $amountBuyerRequest   = 0;
            $amountEliteChallenge = 0;
            $percentDifficulty    = 1;
            $totalSecondary       = 0;
        }

        if ($heistKortzCenter->isFirstTimeInWeek() !== null && !$heistKortzCenter->isFirstTimeInWeek()) {
            $percentDifficulty /= 4;
        }

        return [
            'amountBuyerRequest'   => $amountBuyerRequest,
            'amountEliteChallenge' => $amountEliteChallenge,
            'kortzCenter'          => $kortzCenter,
            'totalMain'            => ($heistKortzCenter->getMainLoot()?->getPrice() * $percentDifficulty) + ($bestResult['price'] / $players),
            'totalMainSpotted'     => ($heistKortzCenter->getMainLoot()?->getPrice() * $percentDifficulty) * 0.75 + ($bestResult['price'] / $players),
            'totalSecondary'       => $totalSecondary + ($bestResult['price'] / $players)
        ];
    }

    public function optimizeRoad(array $routeLoots, int $playersCount): array
    {
        $players    = [];
        $assignment = [];

        for ($player = 1; $player <= $playersCount; $player++) {
            $players[$player] = [
                'player'                 => $player,
                'price'                  => 0.0,
                'remainingPercentageBag' => 100.0,
                'usedPercentageBag'      => 0.0
            ];

            $assignment[$player] = [];
        }

        $this->assignLoots(assignment: $assignment, loots: $routeLoots, players: $players);

        $totalPercentageBag = 0.0;
        $totalPrice         = 0.0;
        $allLoots           = [];

        foreach ($players as $player) {
            $totalPercentageBag += $player['usedPercentageBag'];
            $totalPrice         += $player['price'];
        }

        foreach ($assignment as $playerLoots) {
            foreach ($playerLoots as $loot) {
                $allLoots[] = $loot;
            }
        }

        return [
            'distribution'  => $assignment,
            'loots'         => $allLoots,
            'percentageBag' => round($totalPercentageBag, 2),
            'players'       => $players,
            'price'         => round($totalPrice, 2)
        ];
    }

    private function assignLoots(array &$assignment, array $loots, array &$players): void
    {
        if ($loots === []) {
            return;
        }

        $memo = [];

        for ($index = 0, $count = count($loots); $index < $count; $index++) {
            $target = $this->computeBestValue($loots, $index, $players, $memo);
            $loot   = $loots[$index];

            $lootPercentageBag = (float)$loot['percentageBag'];
            $lootPrice         = (float)$loot['price'];

            $skipContinuation = $this->computeBestValue($loots, $index + 1, $players, $memo);

            if (!$this->isValueBetter($target, $skipContinuation)) {
                continue;
            }

            foreach ($players as $playerNumber => $player) {
                $freeCapacity = 100.0 - $player['usedPercentageBag'];

                if ($lootPercentageBag > $freeCapacity + 0.00001) {
                    continue;
                }

                $updatedPlayers = $players;

                $updatedPlayers[$playerNumber]['usedPercentageBag'] += $lootPercentageBag;

                $continuation = $this->computeBestValue($loots, $index + 1, $updatedPlayers, $memo);

                $candidate = [
                    'price'         => $lootPrice + $continuation['price'],
                    'percentageBag' => $lootPercentageBag + $continuation['percentageBag'],
                ];

                if (!$this->isValueBetter($target, $candidate)) {
                    $players[$playerNumber]['usedPercentageBag']      += $lootPercentageBag;
                    $players[$playerNumber]['remainingPercentageBag'] = 100.0 - $players[$playerNumber]['usedPercentageBag'];
                    $players[$playerNumber]['price']                  += $lootPrice;

                    $assignment[$playerNumber][] = [
                        ...$loot,
                        'partial'            => false,
                        'takenPrice'         => round($lootPrice, 2),
                        'takenPercentageBag' => round($lootPercentageBag, 2)
                    ];

                    break;
                }
            }
        }
    }

    private function compareDistributionProfitability(array $firstDistribution, array $secondDistribution): int
    {
        $firstProfitability  = $firstDistribution['price'] / $firstDistribution['percentageBag'];
        $secondProfitability = $secondDistribution['price'] / $secondDistribution['percentageBag'];

        if (abs($firstProfitability - $secondProfitability) < 0.00001) {
            return $firstDistribution['id'] <=> $secondDistribution['id'];
        }

        return $secondProfitability <=> $firstProfitability;
    }

    private function computeBestValue(array $loots, int $index, array $players, array &$memo): array
    {
        if ($index >= count($loots)) {
            return ['price' => 0.0, 'percentageBag' => 0.0];
        }

        $usedPercentages = array_map(static fn(array $player): float => $player['usedPercentageBag'], $players);
        sort($usedPercentages);

        $stateKey = $index . '|' . implode(',', array_map(static fn(float $used): string => number_format($used, 2), $usedPercentages));

        if (isset($memo[$stateKey])) {
            return $memo[$stateKey];
        }

        $best = $this->computeBestValue($loots, $index + 1, $players, $memo);

        $loot              = $loots[$index];
        $lootPercentageBag = (float)$loot['percentageBag'];
        $lootPrice         = (float)$loot['price'];

        foreach ($players as $playerNumber => $player) {
            $freeCapacity = 100.0 - $player['usedPercentageBag'];

            if ($lootPercentageBag > $freeCapacity + 0.00001) {
                continue;
            }

            $updatedPlayers = $players;

            $updatedPlayers[$playerNumber]['usedPercentageBag'] += $lootPercentageBag;

            $continuation = $this->computeBestValue($loots, $index + 1, $updatedPlayers, $memo);

            $candidate = [
                'price'         => $lootPrice + $continuation['price'],
                'percentageBag' => $lootPercentageBag + $continuation['percentageBag'],
            ];

            if ($this->isValueBetter($candidate, $best)) {
                $best = $candidate;
            }
        }

        $memo[$stateKey] = $best;

        return $best;
    }

    private function formatTakenPercentage(float $percentage): string
    {
        return $this->configurationHelper->formatPercent($percentage, 2, true);
    }

    private function getArea(int $heistKortzCenterSecondaryLootId): string
    {
        return match ($heistKortzCenterSecondaryLootId) {
            1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12, 13                  => 'secondFloor',
            14, 15, 16, 17, 18, 19, 20, 21, 22, 23, 24, 25, 26, 27, 28 => 'groundFloor',
            29, 30, 31, 32                                             => 'vault',
            default                                                    => '',
        };
    }

    private function isValueBetter(array $candidate, array $reference): bool
    {
        return $candidate['price'] > $reference['price'] + 0.00001
               || (abs($candidate['price'] - $reference['price']) < 0.00001
                   && $candidate['percentageBag'] > $reference['percentageBag'] + 0.00001
               );
    }
}
