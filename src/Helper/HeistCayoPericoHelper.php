<?php

namespace App\Helper;

use App\Entity\HeistCayoPerico;
use App\Enum\HeistCayoPericoDifficulty;
use Doctrine\DBAL\Exception as DoctrineDBALException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;

readonly class HeistCayoPericoHelper
{
    private const float EPSILON = 0.00001;

    private const float FIT_TOLERANCE = 0.02;

    private const array OUTSIDE_AREAS = ['zoneOne', 'zoneThree', 'zoneSix'];

    private const float SCORE_CUT_PENALTY = 0.0001;

    private const float SCORE_MAXIMUM_WEIGHT = 0.000001;

    public function __construct(private ConfigurationHelper $configurationHelper, private EntityManagerInterface $entityManager, private Security $security)
    {
    }

    public function applyBonusSecondaryLoot(array $loot, float $bonusSecondaryLoot): array
    {
        $loot['priceMaximum'] *= $bonusSecondaryLoot;
        $loot['priceMinimum'] *= $bonusSecondaryLoot;

        return $loot;
    }

    public function buildPossibleLoots(array $loots, int $players): array
    {
        $possibleLoots = [];

        foreach ($loots as $loot) {
            $accessibleAlone = !in_array($loot['area'], ['basement', 'west', 'north', 'south']);
            $name            = (string)$loot['name'];
            $percentageBag   = (float)($loot['percentageBag'] ?? 0);
            $priceMaximum    = (float)($loot['priceMaximum'] ?? 0);
            $priceMinimum    = (float)($loot['priceMinimum'] ?? 0);
            $quantity        = (int)($loot['quantity'] ?? 0);
            $splittable      = !str_starts_with($name, 'artwork');

            if ($quantity <= 0) {
                continue;
            }

            if ($players === 1 && !$accessibleAlone) {
                continue;
            }

            if ($priceMinimum <= 0 || $percentageBag <= 0) {
                continue;
            }

            for ($number = 1; $number <= $quantity; $number++) {
                $possibleLoots[] = [
                    'area'          => $loot['area'],
                    'name'          => $name,
                    'number'        => $number,
                    'percentageBag' => $percentageBag,
                    'priceMaximum'  => $priceMaximum,
                    'priceMinimum'  => $priceMinimum,
                    'splittable'    => $splittable
                ];
            }
        }

        return $possibleLoots;
    }

    public function getDifficulties(): array
    {
        return [
            HeistCayoPericoDifficulty::NORMAL,
            HeistCayoPericoDifficulty::HARD
        ];
    }

    /**
     * @throws DoctrineDBALException
     */
    public function getHeistCayoPericoMainLoots(): array
    {
        $query = "
            SELECT heist_cayo_perico_main_loot.id, heist_cayo_perico_main_loot_locale.name, heist_cayo_perico_main_loot.price,
                   heist_cayo_perico_main_loot.percentage_first_heist_week AS percentageFirstWeek,
                   heist_cayo_perico_main_loot.percentage_other_heist_week AS percentageOtherWeek,
                   heist_cayo_perico_main_loot.percentage_bonus_secondary_loot AS percentageBonusSecondaryLoot
            FROM heist_cayo_perico_main_loot
            LEFT JOIN heist_cayo_perico_main_loot_locale
                ON heist_cayo_perico_main_loot_locale.heist_cayo_perico_main_loot_id = heist_cayo_perico_main_loot.id
                AND heist_cayo_perico_main_loot_locale.locale_id = :localeId
            ORDER BY heist_cayo_perico_main_loot.price DESC
        ";

        $statement = $this->entityManager->getConnection()->prepare($query);
        $statement->bindValue('localeId', $this->security->getUser()->getLocale()->id);

        return $statement->executeQuery()->fetchAllAssociative();
    }

    /**
     * @throws DoctrineDBALException
     */
    public function getHeistCayoPericoSecondaryLoot(string $heistCayoPericoSecondaryLootName): array
    {
        $query = "
            SELECT heist_cayo_perico_secondary_loot.id, heist_cayo_perico_secondary_loot.name, heist_cayo_perico_secondary_loot.price_minimum AS priceMinimum,
                   heist_cayo_perico_secondary_loot.price_maximum AS priceMaximum, heist_cayo_perico_secondary_loot.percentage_bag AS percentageBag
            FROM heist_cayo_perico_secondary_loot
            WHERE heist_cayo_perico_secondary_loot.name = :heistCayoPericoSecondaryLootName
        ";

        $statement = $this->entityManager->getConnection()->prepare($query);
        $statement->bindValue('heistCayoPericoSecondaryLootName', $heistCayoPericoSecondaryLootName);

        return $statement->executeQuery()->fetchAssociative();
    }

    /**
     * @throws DoctrineDBALException
     */
    public function getHeistCayoPericoSecondaryLoots(): array
    {
        $query = "
            SELECT heist_cayo_perico_secondary_loot.id, heist_cayo_perico_secondary_loot.name, heist_cayo_perico_secondary_loot.price_minimum AS priceMinimum,
                   heist_cayo_perico_secondary_loot.price_maximum AS priceMaximum, heist_cayo_perico_secondary_loot.percentage_bag AS percentageBag,
                   (heist_cayo_perico_secondary_loot.price_minimum * 100 / heist_cayo_perico_secondary_loot.percentage_bag) AS priceMinimumTotal,
                   (heist_cayo_perico_secondary_loot.price_maximum * 100 / heist_cayo_perico_secondary_loot.percentage_bag) AS priceMaximumTotal
            FROM heist_cayo_perico_secondary_loot
            ORDER BY (heist_cayo_perico_secondary_loot.price_minimum * 100 / heist_cayo_perico_secondary_loot.percentage_bag) DESC,
                     (heist_cayo_perico_secondary_loot.price_maximum * 100 / heist_cayo_perico_secondary_loot.percentage_bag) DESC
        ";

        $statement = $this->entityManager->getConnection()->prepare($query);

        return $statement->executeQuery()->fetchAllAssociative();
    }

    /**
     * Valeur acceptée pour un choix du braquage (null = choix vidé, quantités entières positives tenant dans un INT)
     *
     * @throws DoctrineDBALException
     */
    public function isValidValue(string $place, ?string $value): bool
    {
        if ($value === null) {
            return true;
        }

        return match ($place) {
            'difficulty'   => HeistCayoPericoDifficulty::tryFrom($value) !== null,
            'main_loot_id' => (bool)$this->entityManager->getConnection()->fetchOne('SELECT 1 FROM heist_cayo_perico_main_loot WHERE id = ?', [$value]),
            default        => ctype_digit($value) && strlen($value) <= 9
        };
    }

    public function optimizeDistribution(array $possibleLoots, int $playersCount): array
    {
        $classes = $this->buildLootClasses($possibleLoots);
        $stock   = array_map(static fn(array $class): int => count($class['names']), $classes);
        $memo    = [];

        $bestResult = $this->searchBestDistribution(
            classes: $classes, memo: $memo, playersLeft: $playersCount, remainders: [], stock: $stock, threshold: -1.0
        );

        $result = $this->buildDistribution(classes: $classes, plans: $bestResult['plans']);

        for ($player = 1; $player <= $playersCount; $player++) {
            $result['distribution'][$player] ??= [];
        }

        return $result;
    }

    /**
     * @throws DoctrineDBALException
     */
    public function optimizeProfits(HeistCayoPerico $heistCayoPerico, int $players): array
    {
        $bonusSecondaryLoot = 1 + ($heistCayoPerico->getMainLoot()?->getPercentageBonusSecondaryLoot() ?? 0) / 100;

        $lootGold         = $this->applyBonusSecondaryLoot($this->getHeistCayoPericoSecondaryLoot('gold'), $bonusSecondaryLoot);
        $lootCocaine      = $this->applyBonusSecondaryLoot($this->getHeistCayoPericoSecondaryLoot('cocaine'), $bonusSecondaryLoot);
        $lootWeed         = $this->applyBonusSecondaryLoot($this->getHeistCayoPericoSecondaryLoot('weed'), $bonusSecondaryLoot);
        $lootArtwork      = $this->applyBonusSecondaryLoot($this->getHeistCayoPericoSecondaryLoot('artwork'), $bonusSecondaryLoot);
        $lootCash         = $this->applyBonusSecondaryLoot($this->getHeistCayoPericoSecondaryLoot('cash'), $bonusSecondaryLoot);
        $lootDesktopVault = $this->getHeistCayoPericoSecondaryLoot('safe_cash');

        $loots = [
            [
                'area'          => 'zoneOne',
                'name'          => 'cocaineZoneOne',
                'percentageBag' => $lootCocaine['percentageBag'],
                'priceMaximum'  => $lootCocaine['priceMaximum'],
                'priceMinimum'  => $lootCocaine['priceMinimum'],
                'quantity'      => $heistCayoPerico->getCocaineZoneOne()
            ],
            [
                'area'          => 'zoneOne',
                'name'          => 'weedZoneOne',
                'percentageBag' => $lootWeed['percentageBag'],
                'priceMaximum'  => $lootWeed['priceMaximum'],
                'priceMinimum'  => $lootWeed['priceMinimum'],
                'quantity'      => $heistCayoPerico->getWeedZoneOne()
            ],
            [
                'area'          => 'zoneOne',
                'name'          => 'cashZoneOne',
                'percentageBag' => $lootCash['percentageBag'],
                'priceMaximum'  => $lootCash['priceMaximum'],
                'priceMinimum'  => $lootCash['priceMinimum'],
                'quantity'      => $heistCayoPerico->getCashZoneOne()
            ],
            [
                'area'          => 'zoneThree',
                'name'          => 'cocaineZoneThree',
                'percentageBag' => $lootCocaine['percentageBag'],
                'priceMaximum'  => $lootCocaine['priceMaximum'],
                'priceMinimum'  => $lootCocaine['priceMinimum'],
                'quantity'      => $heistCayoPerico->getCocaineZoneThree()
            ],
            [
                'area'          => 'zoneThree',
                'name'          => 'weedZoneThree',
                'percentageBag' => $lootWeed['percentageBag'],
                'priceMaximum'  => $lootWeed['priceMaximum'],
                'priceMinimum'  => $lootWeed['priceMinimum'],
                'quantity'      => $heistCayoPerico->getWeedZoneThree()
            ],
            [
                'area'          => 'zoneThree',
                'name'          => 'cashZoneThree',
                'percentageBag' => $lootCash['percentageBag'],
                'priceMaximum'  => $lootCash['priceMaximum'],
                'priceMinimum'  => $lootCash['priceMinimum'],
                'quantity'      => $heistCayoPerico->getCashZoneThree()
            ],
            [
                'area'          => 'zoneSix',
                'name'          => 'cocaineZoneSix',
                'percentageBag' => $lootCocaine['percentageBag'],
                'priceMaximum'  => $lootCocaine['priceMaximum'],
                'priceMinimum'  => $lootCocaine['priceMinimum'],
                'quantity'      => $heistCayoPerico->getCocaineZoneSix()
            ],
            [
                'area'          => 'zoneSix',
                'name'          => 'weedZoneSix',
                'percentageBag' => $lootWeed['percentageBag'],
                'priceMaximum'  => $lootWeed['priceMaximum'],
                'priceMinimum'  => $lootWeed['priceMinimum'],
                'quantity'      => $heistCayoPerico->getWeedZoneSix()
            ],
            [
                'area'          => 'zoneSix',
                'name'          => 'cashZoneSix',
                'percentageBag' => $lootCash['percentageBag'],
                'priceMaximum'  => $lootCash['priceMaximum'],
                'priceMinimum'  => $lootCash['priceMinimum'],
                'quantity'      => $heistCayoPerico->getCashZoneSix()
            ],
            [
                'area'          => 'basement',
                'name'          => 'goldMainBasement',
                'percentageBag' => $lootGold['percentageBag'],
                'priceMaximum'  => $lootGold['priceMaximum'],
                'priceMinimum'  => $lootGold['priceMinimum'],
                'quantity'      => $heistCayoPerico->getGoldMainBasement()
            ],
            [
                'area'          => 'basement',
                'name'          => 'cocaineMainBasement',
                'percentageBag' => $lootCocaine['percentageBag'],
                'priceMaximum'  => $lootCocaine['priceMaximum'],
                'priceMinimum'  => $lootCocaine['priceMinimum'],
                'quantity'      => $heistCayoPerico->getCocaineMainBasement()
            ],
            [
                'area'          => 'basement',
                'name'          => 'weedMainBasement',
                'percentageBag' => $lootWeed['percentageBag'],
                'priceMaximum'  => $lootWeed['priceMaximum'],
                'priceMinimum'  => $lootWeed['priceMinimum'],
                'quantity'      => $heistCayoPerico->getWeedMainBasement()
            ],
            [
                'area'          => 'basement',
                'name'          => 'artworkMainBasement',
                'percentageBag' => $lootArtwork['percentageBag'],
                'priceMaximum'  => $lootArtwork['priceMaximum'],
                'priceMinimum'  => $lootArtwork['priceMinimum'],
                'quantity'      => $heistCayoPerico->getArtworkMainBasement()
            ],
            [
                'area'          => 'basement',
                'name'          => 'cashMainBasement',
                'percentageBag' => $lootCash['percentageBag'],
                'priceMaximum'  => $lootCash['priceMaximum'],
                'priceMinimum'  => $lootCash['priceMinimum'],
                'quantity'      => $heistCayoPerico->getCashMainBasement()
            ],
            [
                'area'          => 'desktop',
                'name'          => 'artworkMainDesktop',
                'percentageBag' => $lootArtwork['percentageBag'],
                'priceMaximum'  => $lootArtwork['priceMaximum'],
                'priceMinimum'  => $lootArtwork['priceMinimum'],
                'quantity'      => $heistCayoPerico->getArtworkMainDesktop()
            ],
            [
                'area'          => 'west',
                'name'          => 'goldMainWest',
                'percentageBag' => $lootGold['percentageBag'],
                'priceMaximum'  => $lootGold['priceMaximum'],
                'priceMinimum'  => $lootGold['priceMinimum'],
                'quantity'      => $heistCayoPerico->getGoldMainWest()
            ],
            [
                'area'          => 'west',
                'name'          => 'cocaineMainWest',
                'percentageBag' => $lootCocaine['percentageBag'],
                'priceMaximum'  => $lootCocaine['priceMaximum'],
                'priceMinimum'  => $lootCocaine['priceMinimum'],
                'quantity'      => $heistCayoPerico->getCocaineMainWest()
            ],
            [
                'area'          => 'west',
                'name'          => 'weedMainWest',
                'percentageBag' => $lootWeed['percentageBag'],
                'priceMaximum'  => $lootWeed['priceMaximum'],
                'priceMinimum'  => $lootWeed['priceMinimum'],
                'quantity'      => $heistCayoPerico->getWeedMainWest()
            ],
            [
                'area'          => 'west',
                'name'          => 'artworkMainWest',
                'percentageBag' => $lootArtwork['percentageBag'],
                'priceMaximum'  => $lootArtwork['priceMaximum'],
                'priceMinimum'  => $lootArtwork['priceMinimum'],
                'quantity'      => $heistCayoPerico->getArtworkMainWest()
            ],
            [
                'area'          => 'west',
                'name'          => 'cashMainWest',
                'percentageBag' => $lootCash['percentageBag'],
                'priceMaximum'  => $lootCash['priceMaximum'],
                'priceMinimum'  => $lootCash['priceMinimum'],
                'quantity'      => $heistCayoPerico->getCashMainWest()
            ],
            [
                'area'          => 'north',
                'name'          => 'goldMainNorth',
                'percentageBag' => $lootGold['percentageBag'],
                'priceMaximum'  => $lootGold['priceMaximum'],
                'priceMinimum'  => $lootGold['priceMinimum'],
                'quantity'      => $heistCayoPerico->getGoldMainNorth()
            ],
            [
                'area'          => 'north',
                'name'          => 'cocaineMainNorth',
                'percentageBag' => $lootCocaine['percentageBag'],
                'priceMaximum'  => $lootCocaine['priceMaximum'],
                'priceMinimum'  => $lootCocaine['priceMinimum'],
                'quantity'      => $heistCayoPerico->getCocaineMainNorth()
            ],
            [
                'area'          => 'north',
                'name'          => 'weedMainNorth',
                'percentageBag' => $lootWeed['percentageBag'],
                'priceMaximum'  => $lootWeed['priceMaximum'],
                'priceMinimum'  => $lootWeed['priceMinimum'],
                'quantity'      => $heistCayoPerico->getWeedMainNorth()
            ],
            [
                'area'          => 'north',
                'name'          => 'artworkMainNorth',
                'percentageBag' => $lootArtwork['percentageBag'],
                'priceMaximum'  => $lootArtwork['priceMaximum'],
                'priceMinimum'  => $lootArtwork['priceMinimum'],
                'quantity'      => $heistCayoPerico->getArtworkMainNorth()
            ],
            [
                'area'          => 'north',
                'name'          => 'cashMainNorth',
                'percentageBag' => $lootCash['percentageBag'],
                'priceMaximum'  => $lootCash['priceMaximum'],
                'priceMinimum'  => $lootCash['priceMinimum'],
                'quantity'      => $heistCayoPerico->getCashMainNorth()
            ],
            [
                'area'          => 'south',
                'name'          => 'goldMainSouth',
                'percentageBag' => $lootGold['percentageBag'],
                'priceMaximum'  => $lootGold['priceMaximum'],
                'priceMinimum'  => $lootGold['priceMinimum'],
                'quantity'      => $heistCayoPerico->getGoldMainSouth()
            ],
            [
                'area'          => 'south',
                'name'          => 'cocaineMainSouth',
                'percentageBag' => $lootCocaine['percentageBag'],
                'priceMaximum'  => $lootCocaine['priceMaximum'],
                'priceMinimum'  => $lootCocaine['priceMinimum'],
                'quantity'      => $heistCayoPerico->getCocaineMainSouth()
            ],
            [
                'area'          => 'south',
                'name'          => 'weedMainSouth',
                'percentageBag' => $lootWeed['percentageBag'],
                'priceMaximum'  => $lootWeed['priceMaximum'],
                'priceMinimum'  => $lootWeed['priceMinimum'],
                'quantity'      => $heistCayoPerico->getWeedMainSouth()
            ],
            [
                'area'          => 'south',
                'name'          => 'artworkMainSouth',
                'percentageBag' => $lootArtwork['percentageBag'],
                'priceMaximum'  => $lootArtwork['priceMaximum'],
                'priceMinimum'  => $lootArtwork['priceMinimum'],
                'quantity'      => $heistCayoPerico->getArtworkMainSouth()
            ],
            [
                'area'          => 'south',
                'name'          => 'cashMainSouth',
                'percentageBag' => $lootCash['percentageBag'],
                'priceMaximum'  => $lootCash['priceMaximum'],
                'priceMinimum'  => $lootCash['priceMinimum'],
                'quantity'      => $heistCayoPerico->getCashMainSouth()
            ],
        ];

        $possibleLoots = $this->buildPossibleLoots(loots: $loots, players: $players);
        $bestResult    = $this->optimizeDistribution(possibleLoots: $possibleLoots, playersCount: $players);

        $zoneOne      = [];
        $zoneThree    = [];
        $zoneSix      = [];
        $mainBasement = [];
        $mainDesktop  = [];
        $mainWest     = [];
        $mainNorth    = [];
        $mainSouth    = [];

        for ($i = 1; $i <= $players; $i++) {
            $cocaineZoneOne[$i]           = 0;
            $cocaineZoneOnePercentage[$i] = 0.0;
            $weedZoneOne[$i]              = 0;
            $weedZoneOnePercentage[$i]    = 0.0;
            $cashZoneOne[$i]              = 0;
            $cashZoneOnePercentage[$i]    = 0.0;
            $zoneOne[$i]                  = '';

            $cocaineZoneThree[$i]           = 0;
            $cocaineZoneThreePercentage[$i] = 0.0;
            $weedZoneThree[$i]              = 0;
            $weedZoneThreePercentage[$i]    = 0.0;
            $cashZoneThree[$i]              = 0;
            $cashZoneThreePercentage[$i]    = 0.0;
            $zoneThree[$i]                  = '';

            $cocaineZoneSix[$i]           = 0;
            $cocaineZoneSixPercentage[$i] = 0.0;
            $weedZoneSix[$i]              = 0;
            $weedZoneSixPercentage[$i]    = 0.0;
            $cashZoneSix[$i]              = 0;
            $cashZoneSixPercentage[$i]    = 0.0;
            $zoneSix[$i]                  = '';

            $goldMainBasement[$i]              = 0;
            $goldMainBasementPercentage[$i]    = 0.0;
            $cocaineMainBasement[$i]           = 0;
            $cocaineMainBasementPercentage[$i] = 0.0;
            $cashMainBasement[$i]              = 0;
            $cashMainBasementPercentage[$i]    = 0.0;
            $artworkMainBasement[$i]           = 0;
            $artworkMainBasementPercentage[$i] = 0.0;
            $weedMainBasement[$i]              = 0;
            $weedMainBasementPercentage[$i]    = 0.0;
            $mainBasement[$i]                  = '';

            $artworkMainDesktop[$i]           = 0;
            $artworkMainDesktopPercentage[$i] = 0.0;
            $mainDesktop[$i]                  = '';

            $goldMainWest[$i]              = 0;
            $goldMainWestPercentage[$i]    = 0.0;
            $cocaineMainWest[$i]           = 0;
            $cocaineMainWestPercentage[$i] = 0.0;
            $cashMainWest[$i]              = 0;
            $cashMainWestPercentage[$i]    = 0.0;
            $artworkMainWest[$i]           = 0;
            $artworkMainWestPercentage[$i] = 0.0;
            $weedMainWest[$i]              = 0;
            $weedMainWestPercentage[$i]    = 0.0;
            $mainWest[$i]                  = '';

            $goldMainNorth[$i]              = 0;
            $goldMainNorthPercentage[$i]    = 0.0;
            $cocaineMainNorth[$i]           = 0;
            $cocaineMainNorthPercentage[$i] = 0.0;
            $cashMainNorth[$i]              = 0;
            $cashMainNorthPercentage[$i]    = 0.0;
            $artworkMainNorth[$i]           = 0;
            $artworkMainNorthPercentage[$i] = 0.0;
            $weedMainNorth[$i]              = 0;
            $weedMainNorthPercentage[$i]    = 0.0;
            $mainNorth[$i]                  = '';

            $goldMainSouth[$i]              = 0;
            $goldMainSouthPercentage[$i]    = 0.0;
            $cocaineMainSouth[$i]           = 0;
            $cocaineMainSouthPercentage[$i] = 0.0;
            $cashMainSouth[$i]              = 0;
            $cashMainSouthPercentage[$i]    = 0.0;
            $artworkMainSouth[$i]           = 0;
            $artworkMainSouthPercentage[$i] = 0.0;
            $weedMainSouth[$i]              = 0;
            $weedMainSouthPercentage[$i]    = 0.0;
            $mainSouth[$i]                  = '';

            if (!empty($bestResult['distribution'][$i])) {
                foreach ($bestResult['distribution'][$i] as $distribution) {
                    $distributionName = $distribution['name'];

                    $counterMap = [
                        'cocaineZoneOne' => &$cocaineZoneOne,
                        'cashZoneOne'    => &$cashZoneOne,
                        'weedZoneOne'    => &$weedZoneOne,

                        'cocaineZoneThree' => &$cocaineZoneThree,
                        'cashZoneThree'    => &$cashZoneThree,
                        'weedZoneThree'    => &$weedZoneThree,

                        'cocaineZoneSix' => &$cocaineZoneSix,
                        'cashZoneSix'    => &$cashZoneSix,
                        'weedZoneSix'    => &$weedZoneSix,

                        'goldMainBasement'    => &$goldMainBasement,
                        'cocaineMainBasement' => &$cocaineMainBasement,
                        'cashMainBasement'    => &$cashMainBasement,
                        'artworkMainBasement' => &$artworkMainBasement,
                        'weedMainBasement'    => &$weedMainBasement,

                        'artworkMainDesktop'  => &$artworkMainDesktop,

                        'goldMainWest'    => &$goldMainWest,
                        'cocaineMainWest' => &$cocaineMainWest,
                        'cashMainWest'    => &$cashMainWest,
                        'artworkMainWest' => &$artworkMainWest,
                        'weedMainWest'    => &$weedMainWest,

                        'goldMainNorth'    => &$goldMainNorth,
                        'cocaineMainNorth' => &$cocaineMainNorth,
                        'cashMainNorth'    => &$cashMainNorth,
                        'artworkMainNorth' => &$artworkMainNorth,
                        'weedMainNorth'    => &$weedMainNorth,

                        'goldMainSouth'    => &$goldMainSouth,
                        'cocaineMainSouth' => &$cocaineMainSouth,
                        'cashMainSouth'    => &$cashMainSouth,
                        'artworkMainSouth' => &$artworkMainSouth,
                        'weedMainSouth'    => &$weedMainSouth
                    ];

                    $percentageMap = [
                        'cocaineZoneOne' => &$cocaineZoneOnePercentage,
                        'cashZoneOne'    => &$cashZoneOnePercentage,
                        'weedZoneOne'    => &$weedZoneOnePercentage,

                        'cocaineZoneThree' => &$cocaineZoneThreePercentage,
                        'cashZoneThree'    => &$cashZoneThreePercentage,
                        'weedZoneThree'    => &$weedZoneThreePercentage,

                        'cocaineZoneSix' => &$cocaineZoneSixPercentage,
                        'cashZoneSix'    => &$cashZoneSixPercentage,
                        'weedZoneSix'    => &$weedZoneSixPercentage,

                        'goldMainBasement'    => &$goldMainBasementPercentage,
                        'cocaineMainBasement' => &$cocaineMainBasementPercentage,
                        'cashMainBasement'    => &$cashMainBasementPercentage,
                        'artworkMainBasement' => &$artworkMainBasementPercentage,
                        'weedMainBasement'    => &$weedMainBasementPercentage,

                        'artworkMainDesktop'  => &$artworkMainDesktopPercentage,

                        'goldMainWest'    => &$goldMainWestPercentage,
                        'cocaineMainWest' => &$cocaineMainWestPercentage,
                        'cashMainWest'    => &$cashMainWestPercentage,
                        'artworkMainWest' => &$artworkMainWestPercentage,
                        'weedMainWest'    => &$weedMainWestPercentage,

                        'goldMainNorth'    => &$goldMainNorthPercentage,
                        'cocaineMainNorth' => &$cocaineMainNorthPercentage,
                        'cashMainNorth'    => &$cashMainNorthPercentage,
                        'artworkMainNorth' => &$artworkMainNorthPercentage,
                        'weedMainNorth'    => &$weedMainNorthPercentage,

                        'goldMainSouth'    => &$goldMainSouthPercentage,
                        'cocaineMainSouth' => &$cocaineMainSouthPercentage,
                        'cashMainSouth'    => &$cashMainSouthPercentage,
                        'artworkMainSouth' => &$artworkMainSouthPercentage,
                        'weedMainSouth'    => &$weedMainSouthPercentage
                    ];

                    if (isset($counterMap[$distributionName])) {
                        $counterMap[$distributionName][$i]++;
                        $percentageMap[$distributionName][$i] += (float)$distribution['takenPercentageBag'];
                    }
                }
            }

            $zoneOneParts      = [];
            $zoneThreeParts    = [];
            $zoneSixParts      = [];
            $mainBasementParts = [];
            $mainDesktopParts  = [];
            $mainWestParts     = [];
            $mainNorthParts    = [];
            $mainSouthParts    = [];

            if ($cocaineZoneOne[$i] != 0) {
                $zoneOneParts[] = $this->formatLootQuantity('cocaine', $cocaineZoneOne[$i], $cocaineZoneOnePercentage[$i]);
            }

            if ($weedZoneOne[$i] != 0) {
                $zoneOneParts[] = $this->formatLootQuantity('weed', $weedZoneOne[$i], $weedZoneOnePercentage[$i]);
            }

            if ($cashZoneOne[$i] != 0) {
                $zoneOneParts[] = $this->formatLootQuantity('cash', $cashZoneOne[$i], $cashZoneOnePercentage[$i]);
            }

            if ($zoneOneParts !== []) {
                $zoneOne[$i] .= implode('<br/>', $zoneOneParts);
            }

            if ($cocaineZoneThree[$i] != 0) {
                $zoneThreeParts[] = $this->formatLootQuantity('cocaine', $cocaineZoneThree[$i], $cocaineZoneThreePercentage[$i]);
            }

            if ($weedZoneThree[$i] != 0) {
                $zoneThreeParts[] = $this->formatLootQuantity('weed', $weedZoneThree[$i], $weedZoneThreePercentage[$i]);
            }

            if ($cashZoneThree[$i] != 0) {
                $zoneThreeParts[] = $this->formatLootQuantity('cash', $cashZoneThree[$i], $cashZoneThreePercentage[$i]);
            }

            if ($zoneThreeParts !== []) {
                $zoneThree[$i] .= implode('<br/>', $zoneThreeParts);
            }

            if ($cocaineZoneSix[$i] != 0) {
                $zoneSixParts[] = $this->formatLootQuantity('cocaine', $cocaineZoneSix[$i], $cocaineZoneSixPercentage[$i]);
            }

            if ($weedZoneSix[$i] != 0) {
                $zoneSixParts[] = $this->formatLootQuantity('weed', $weedZoneSix[$i], $weedZoneSixPercentage[$i]);
            }

            if ($cashZoneSix[$i] != 0) {
                $zoneSixParts[] = $this->formatLootQuantity('cash', $cashZoneSix[$i], $cashZoneSixPercentage[$i]);
            }

            if ($zoneSixParts !== []) {
                $zoneSix[$i] .= implode('<br/>', $zoneSixParts);
            }

            if ($goldMainBasement[$i] != 0) {
                $mainBasementParts[] = $this->formatLootQuantity('gold', $goldMainBasement[$i], $goldMainBasementPercentage[$i]);
            }

            if ($cocaineMainBasement[$i] != 0) {
                $mainBasementParts[] = $this->formatLootQuantity('cocaine', $cocaineMainBasement[$i], $cocaineMainBasementPercentage[$i]);
            }

            if ($weedMainBasement[$i] != 0) {
                $mainBasementParts[] = $this->formatLootQuantity('weed', $weedMainBasement[$i], $weedMainBasementPercentage[$i]);
            }

            if ($artworkMainBasement[$i] != 0) {
                $mainBasementParts[] = $this->formatLootQuantity('artwork', $artworkMainBasement[$i], $artworkMainBasementPercentage[$i]);
            }

            if ($cashMainBasement[$i] != 0) {
                $mainBasementParts[] = $this->formatLootQuantity('cash', $cashMainBasement[$i], $cashMainBasementPercentage[$i]);
            }

            if ($mainBasementParts !== []) {
                $mainBasement[$i] .= implode('<br/>', $mainBasementParts);
            }

            if ($artworkMainDesktop[$i] != 0) {
                $mainDesktopParts[] = $this->formatLootQuantity('artwork', $artworkMainDesktop[$i], $artworkMainDesktopPercentage[$i]);
            }

            if ($mainDesktopParts !== []) {
                $mainDesktop[$i] .= implode('<br/>', $mainDesktopParts);
            }

            if ($goldMainWest[$i] != 0) {
                $mainWestParts[] = $this->formatLootQuantity('gold', $goldMainWest[$i], $goldMainWestPercentage[$i]);
            }

            if ($cocaineMainWest[$i] != 0) {
                $mainWestParts[] = $this->formatLootQuantity('cocaine', $cocaineMainWest[$i], $cocaineMainWestPercentage[$i]);
            }

            if ($weedMainWest[$i] != 0) {
                $mainWestParts[] = $this->formatLootQuantity('weed', $weedMainWest[$i], $weedMainWestPercentage[$i]);
            }

            if ($artworkMainWest[$i] != 0) {
                $mainWestParts[] = $this->formatLootQuantity('artwork', $artworkMainWest[$i], $artworkMainWestPercentage[$i]);
            }

            if ($cashMainWest[$i] != 0) {
                $mainWestParts[] = $this->formatLootQuantity('cash', $cashMainWest[$i], $cashMainWestPercentage[$i]);
            }

            if ($mainWestParts !== []) {
                $mainWest[$i] .= implode('<br/>', $mainWestParts);
            }

            if ($goldMainNorth[$i] != 0) {
                $mainNorthParts[] = $this->formatLootQuantity('gold', $goldMainNorth[$i], $goldMainNorthPercentage[$i]);
            }

            if ($cocaineMainNorth[$i] != 0) {
                $mainNorthParts[] = $this->formatLootQuantity('cocaine', $cocaineMainNorth[$i], $cocaineMainNorthPercentage[$i]);
            }

            if ($weedMainNorth[$i] != 0) {
                $mainNorthParts[] = $this->formatLootQuantity('weed', $weedMainNorth[$i], $weedMainNorthPercentage[$i]);
            }

            if ($artworkMainNorth[$i] != 0) {
                $mainNorthParts[] = $this->formatLootQuantity('artwork', $artworkMainNorth[$i], $artworkMainNorthPercentage[$i]);
            }

            if ($cashMainNorth[$i] != 0) {
                $mainNorthParts[] = $this->formatLootQuantity('cash', $cashMainNorth[$i], $cashMainNorthPercentage[$i]);
            }

            if ($mainNorthParts !== []) {
                $mainNorth[$i] .= implode('<br/>', $mainNorthParts);
            }

            if ($goldMainSouth[$i] != 0) {
                $mainSouthParts[] = $this->formatLootQuantity('gold', $goldMainSouth[$i], $goldMainSouthPercentage[$i]);
            }

            if ($cocaineMainSouth[$i] != 0) {
                $mainSouthParts[] = $this->formatLootQuantity('cocaine', $cocaineMainSouth[$i], $cocaineMainSouthPercentage[$i]);
            }

            if ($weedMainSouth[$i] != 0) {
                $mainSouthParts[] = $this->formatLootQuantity('weed', $weedMainSouth[$i], $weedMainSouthPercentage[$i]);
            }

            if ($artworkMainSouth[$i] != 0) {
                $mainSouthParts[] = $this->formatLootQuantity('artwork', $artworkMainSouth[$i], $artworkMainSouthPercentage[$i]);
            }

            if ($cashMainSouth[$i] != 0) {
                $mainSouthParts[] = $this->formatLootQuantity('cash', $cashMainSouth[$i], $cashMainSouthPercentage[$i]);
            }

            if ($mainSouthParts !== []) {
                $mainSouth[$i] .= implode('<br/>', $mainSouthParts);
            }
        }

        $priceMainLoot = (float)($heistCayoPerico->getMainLoot()?->getPrice() ?? 0);

        if ($heistCayoPerico->getDifficulty() == HeistCayoPericoDifficulty::NORMAL) {
            $amountEliteChallenge = 50000;
        } elseif ($heistCayoPerico->getDifficulty() == HeistCayoPericoDifficulty::HARD) {
            $amountEliteChallenge = 100000;
            $priceMainLoot        *= 1.1;
        } else {
            $amountEliteChallenge = 0;
        }

        return [
            'amountEliteChallenge' => $amountEliteChallenge,
            'mainBasement'         => $mainBasement,
            'mainDesktop'          => $mainDesktop,
            'mainNorth'            => $mainNorth,
            'mainSouth'            => $mainSouth,
            'mainWest'             => $mainWest,
            'totalMaximum'         => $priceMainLoot + $bestResult['priceMaximum'] + $lootDesktopVault['priceMaximum'],
            'totalMinimum'         => $priceMainLoot + $bestResult['priceMinimum'] + $lootDesktopVault['priceMinimum'],
            'zoneOne'              => $zoneOne,
            'zoneSix'              => $zoneSix,
            'zoneThree'            => $zoneThree
        ];
    }

    private function addTakenPiece(array $state, array $class, string $classKey, float $percentageBag, string $type, ?float $remainderSize = null): array
    {
        $ratio = $percentageBag / $class['percentageBag'];

        $state['actions'][]          = ['class' => $classKey, 'percentageBag' => $percentageBag, 'remainderSize' => $remainderSize, 'type' => $type];
        $state['compound']           = $state['compound'] || $class['compound'];
        $state['priceMaximum']      += $ratio * $class['priceMaximum'];
        $state['priceMinimum']      += $ratio * $class['priceMinimum'];
        $state['usedPercentageBag'] += $percentageBag;

        return $state;
    }

    private function buildDistribution(array $classes, array $plans): array
    {
        $availableNames = array_map(static fn(array $class): array => $class['names'], $classes);
        $distribution   = [];
        $priceMaximum   = 0.0;
        $priceMinimum   = 0.0;
        $remainders     = [];

        foreach ($plans as $index => $actions) {
            $player                = $index + 1;
            $distribution[$player] = [];

            foreach ($actions as $action) {
                $class = $classes[$action['class']];

                if ($action['type'] === 'full' || $action['type'] === 'cut') {
                    $name = array_shift($availableNames[$action['class']]);

                    if ($action['type'] === 'cut') {
                        $remainders[] = [
                            'class'         => $action['class'],
                            'name'          => $name,
                            'percentageBag' => round($class['percentageBag'] - $action['percentageBag'], 4)
                        ];
                    }
                } else {
                    $remainderIndex = array_key_first(
                        array_filter(
                            $remainders, static fn(array $remainder): bool => $remainder['class'] === $action['class']
                                && abs($remainder['percentageBag'] - $action['remainderSize']) < 0.0001
                        )
                    );
                    $name           = $remainders[$remainderIndex]['name'];

                    if ($action['type'] === 'remainder') {
                        unset($remainders[$remainderIndex]);
                    } else {
                        $remainders[$remainderIndex]['percentageBag'] = round($remainders[$remainderIndex]['percentageBag'] - $action['percentageBag'], 4);
                    }
                }

                $ratio             = $action['percentageBag'] / $class['percentageBag'];
                $takenPriceMaximum = $ratio * $class['priceMaximum'];
                $takenPriceMinimum = $ratio * $class['priceMinimum'];

                $distribution[$player][] = [
                    'name'               => $name,
                    'partial'            => $action['type'] === 'cut' || $action['type'] === 'cutRemainder',
                    'takenPercentageBag' => round($action['percentageBag'], 2),
                    'takenPriceMaximum'  => round($takenPriceMaximum, 2),
                    'takenPriceMinimum'  => round($takenPriceMinimum, 2)
                ];

                $priceMaximum += $takenPriceMaximum;
                $priceMinimum += $takenPriceMinimum;
            }
        }

        return [
            'distribution' => $distribution,
            'priceMaximum' => round($priceMaximum, 2),
            'priceMinimum' => round($priceMinimum, 2)
        ];
    }

    private function buildLootClasses(array $possibleLoots): array
    {
        $classes = [];

        foreach ($possibleLoots as $loot) {
            $compound = !in_array($loot['area'], self::OUTSIDE_AREAS, true);
            $type     = preg_replace('/[A-Z].*$/', '', $loot['name']);
            $classKey = ($compound ? 'compound' : 'outside') . '|' . $type;

            $classes[$classKey] ??= [
                'compound'      => $compound,
                'names'         => [],
                'percentageBag' => $loot['percentageBag'],
                'priceMaximum'  => $loot['priceMaximum'],
                'priceMinimum'  => $loot['priceMinimum'],
                'splittable'    => $loot['splittable']
            ];

            $classes[$classKey]['names'][] = $loot['name'];
        }

        return $classes;
    }

    private function buildPlayerPlan(array $remainders, array $state, array $stock): array
    {
        usort(
            $remainders, static fn(array $first, array $second): int => [$first['class'], $first['size']] <=> [$second['class'], $second['size']]
        );

        $cuts = count(array_filter($state['actions'], static fn(array $action): bool => $action['type'] === 'cut' || $action['type'] === 'cutRemainder'));

        return [
            'actions'      => $state['actions'],
            'priceMaximum' => $state['priceMaximum'],
            'priceMinimum' => $state['priceMinimum'],
            'remainders'   => $remainders,
            'score'        => $this->getScore(priceMinimum: $state['priceMinimum'], priceMaximum: $state['priceMaximum']) - $cuts * self::SCORE_CUT_PENALTY,
            'stock'        => $stock
        ];
    }

    private function buildPlayerPlans(array $classes, int $classIndex, array &$plans, int $remainderIndex, array $remainders, array $state, array $stock): void
    {
        $classKeys = array_keys($classes);

        if ($classIndex < count($classKeys)) {
            $classKey = $classKeys[$classIndex];
            $class    = $classes[$classKey];

            $this->buildPlayerPlans(
                classes: $classes, classIndex: $classIndex + 1, plans: $plans, remainderIndex: $remainderIndex, remainders: $remainders, state: $state, stock: $stock
            );

            while ($stock[$classKey] > 0 && $state['usedPercentageBag'] + $class['percentageBag'] <= 100.0 + self::FIT_TOLERANCE) {
                $stock[$classKey]--;

                $state = $this->addTakenPiece(state: $state, class: $class, classKey: $classKey, percentageBag: $class['percentageBag'], type: 'full');

                $this->buildPlayerPlans(
                    classes: $classes, classIndex: $classIndex + 1, plans: $plans, remainderIndex: $remainderIndex, remainders: $remainders, state: $state, stock: $stock
                );
            }

            return;
        }

        if ($remainderIndex < count($remainders)) {
            $this->buildPlayerPlans(
                classes: $classes, classIndex: $classIndex, plans: $plans, remainderIndex: $remainderIndex + 1, remainders: $remainders, state: $state, stock: $stock
            );

            $remainder = $remainders[$remainderIndex];

            if ($state['usedPercentageBag'] + $remainder['size'] <= 100.0 + self::FIT_TOLERANCE) {
                $remainders[$remainderIndex]['taken'] = true;

                $state = $this->addTakenPiece(
                    state        : $state,
                    class        : $classes[$remainder['class']],
                    classKey     : $remainder['class'],
                    percentageBag: $remainder['size'],
                    type         : 'remainder',
                    remainderSize: $remainder['size']
                );

                $this->buildPlayerPlans(
                    classes: $classes, classIndex: $classIndex, plans: $plans, remainderIndex: $remainderIndex + 1, remainders: $remainders, state: $state, stock: $stock
                );
            }

            return;
        }

        $leftRemainders    = array_values(array_filter($remainders, static fn(array $remainder): bool => empty($remainder['taken'])));
        $freePercentageBag = 100.0 - $state['usedPercentageBag'];
        $isFull            = true;

        foreach ($classes as $classKey => $class) {
            if ($stock[$classKey] > 0 && $class['percentageBag'] <= $freePercentageBag + self::FIT_TOLERANCE) {
                $isFull = false;
            }
        }

        foreach ($leftRemainders as $remainder) {
            if ($remainder['size'] <= $freePercentageBag + self::FIT_TOLERANCE) {
                $isFull = false;
            }
        }

        if ($isFull) {
            $plans[] = $this->buildPlayerPlan(remainders: $leftRemainders, state: $state, stock: $stock);
        }

        if ($freePercentageBag <= self::FIT_TOLERANCE) {
            return;
        }

        foreach ($classes as $classKey => $class) {
            if (!$class['splittable'] || ($state['compound'] && !$class['compound'])) {
                continue;
            }

            if ($stock[$classKey] > 0 && $class['percentageBag'] > $freePercentageBag + self::FIT_TOLERANCE) {
                $cutStock = $stock;
                $cutStock[$classKey]--;

                $plans[] = $this->buildPlayerPlan(
                    remainders: [...$leftRemainders, ['class' => $classKey, 'size' => round($class['percentageBag'] - $freePercentageBag, 4)]],
                    state     : $this->addTakenPiece(state: $state, class: $class, classKey: $classKey, percentageBag: $freePercentageBag, type: 'cut'),
                    stock     : $cutStock
                );
            }

            foreach ($leftRemainders as $index => $remainder) {
                if ($remainder['class'] !== $classKey || $remainder['size'] <= $freePercentageBag + self::FIT_TOLERANCE) {
                    continue;
                }

                $cutRemainders                 = $leftRemainders;
                $cutRemainders[$index]['size'] = round($remainder['size'] - $freePercentageBag, 4);

                $plans[] = $this->buildPlayerPlan(
                    remainders: $cutRemainders,
                    state     : $this->addTakenPiece(
                        state        : $state,
                        class        : $class,
                        classKey     : $classKey,
                        percentageBag: $freePercentageBag,
                        type         : 'cutRemainder',
                        remainderSize: $remainder['size']
                    ),
                    stock     : $stock
                );
            }
        }
    }

    private function estimateMaximumScore(array $classes, float $percentageBag, array $remainders, array $stock): float
    {
        $pieces = [];

        foreach ($classes as $classKey => $class) {
            if ($stock[$classKey] > 0) {
                $pieces[] = [
                    'percentageBag'   => $stock[$classKey] * $class['percentageBag'],
                    'scorePerPercent' => $this->getScore(priceMinimum: $class['priceMinimum'], priceMaximum: $class['priceMaximum']) / $class['percentageBag']
                ];
            }
        }

        foreach ($remainders as $remainder) {
            $class    = $classes[$remainder['class']];
            $pieces[] = [
                'percentageBag'   => $remainder['size'],
                'scorePerPercent' => $this->getScore(priceMinimum: $class['priceMinimum'], priceMaximum: $class['priceMaximum']) / $class['percentageBag']
            ];
        }

        usort($pieces, static fn(array $first, array $second): int => $second['scorePerPercent'] <=> $first['scorePerPercent']);

        $score = 0.0;

        foreach ($pieces as $piece) {
            if ($percentageBag <= self::EPSILON) {
                break;
            }

            $taken = min($percentageBag, $piece['percentageBag']);

            $score         += $taken * $piece['scorePerPercent'];
            $percentageBag -= $taken;
        }

        return $score;
    }

    /**
     * @throws DoctrineDBALException
     */
    private function formatLootQuantity(string $loot, int $quantity, float $percentage): string
    {
        $lootName = $this->configurationHelper->mbUcfirst(
            $this->configurationHelper->getTranslateLocale('heist_cayo_perico_secondary_loot', ['loot' => $loot])
        );

        return $this->configurationHelper->getTranslateLocale(
            'heist_cayo_perico_loot_quantity', ['loot' => $lootName, 'percentage' => round($percentage, 2), 'quantity' => $quantity]
        );
    }

    private function getScore(float $priceMinimum, float $priceMaximum): float
    {
        return $priceMinimum + $priceMaximum * self::SCORE_MAXIMUM_WEIGHT;
    }

    private function searchBestDistribution(array $classes, array &$memo, int $playersLeft, array $remainders, array $stock, float $threshold): array
    {
        if ($playersLeft === 0) {
            return ['exact' => true, 'plans' => [], 'score' => 0.0];
        }

        $memoKey = $playersLeft . '|' . json_encode($stock) . '|' . json_encode($remainders);

        if (isset($memo[$memoKey]) && ($memo[$memoKey]['exact'] || $memo[$memoKey]['score'] <= $threshold + self::EPSILON)) {
            return $memo[$memoKey];
        }

        $plans = [];

        $this->buildPlayerPlans(
            classes       : $classes,
            classIndex    : 0,
            plans         : $plans,
            remainderIndex: 0,
            remainders    : $remainders,
            state         : ['actions' => [], 'compound' => false, 'priceMaximum' => 0.0, 'priceMinimum' => 0.0, 'usedPercentageBag' => 0.0],
            stock         : $stock
        );

        usort($plans, static fn(array $first, array $second): int => $second['score'] <=> $first['score']);

        $bestResult = null;
        $upperBound = -INF;

        foreach ($plans as $plan) {
            $currentThreshold = max($threshold, $bestResult['score'] ?? -INF);
            $maximumScore     = $plan['score'] + $this->estimateMaximumScore(
                    classes      : $classes,
                    percentageBag: ($playersLeft - 1) * 100.0,
                    remainders   : $plan['remainders'],
                    stock        : $plan['stock']
                );

            if ($maximumScore <= $currentThreshold + self::EPSILON) {
                $upperBound = max($upperBound, $maximumScore);

                continue;
            }

            $nextResult = $this->searchBestDistribution(
                classes    : $classes,
                memo       : $memo,
                playersLeft: $playersLeft - 1,
                remainders : $plan['remainders'],
                stock      : $plan['stock'],
                threshold  : $currentThreshold - $plan['score']
            );
            $score      = $plan['score'] + $nextResult['score'];

            if ($nextResult['exact'] && $score > $currentThreshold + self::EPSILON) {
                $bestResult = ['exact' => true, 'plans' => [$plan['actions'], ...$nextResult['plans']], 'score' => $score];
            } else {
                $upperBound = max($upperBound, $score);
            }
        }

        $memo[$memoKey] = $bestResult ?? ['exact' => false, 'plans' => [], 'score' => min($upperBound, $memo[$memoKey]['score'] ?? INF)];

        return $memo[$memoKey];
    }
}
