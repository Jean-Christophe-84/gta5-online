<?php

namespace App\Helper;

use App\Entity\HeistCasino;
use App\Enum\HeistCasinoApproach;
use App\Enum\HeistCasinoDifficulty;
use Doctrine\DBAL\Exception as DoctrineDBALException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;

readonly class HeistCasinoHelper
{
    public function __construct(
        private ConfigurationHelper $configurationHelper, private EntityManagerInterface $entityManager, private Security $security,
        private VehicleHelper       $vehicleHelper, private WeaponHelper $weaponHelper
    ) {
    }

    public function getApproaches(): array
    {
        return [
            HeistCasinoApproach::CUNNING,
            HeistCasinoApproach::STEALTHY,
            HeistCasinoApproach::BRUTAL
        ];
    }

    /**
     * @throws DoctrineDBALException
     */
    public function getApproachLabel(string $approach): string
    {
        return $this->configurationHelper->mbUcfirst($this->configurationHelper->getTranslateLocale(HeistCasinoApproach::from($approach)));
    }

    public function getDifficulties(): array
    {
        return [
            HeistCasinoDifficulty::NORMAL,
            HeistCasinoDifficulty::HARD
        ];
    }

    /**
     * @throws DoctrineDBALException
     */
    public function getHeistCasinoDrivers(): array
    {
        $query = "
            SELECT heist_casino_driver.id, heist_casino_driver.name, heist_casino_driver.percent,
                   GROUP_CONCAT(DISTINCT heist_casino_driver_vehicle.vehicle_id ORDER BY heist_casino_driver_vehicle.position SEPARATOR '+') AS heistCasinoDriverVehicles
            FROM heist_casino_driver
            LEFT JOIN heist_casino_driver_vehicle ON heist_casino_driver_vehicle.heist_casino_driver_id = heist_casino_driver.id
            GROUP BY heist_casino_driver.id
            ORDER BY heist_casino_driver.percent DESC
        ";

        $statement = $this->entityManager->getConnection()->prepare($query);

        if (!empty($heistCasinoDrivers = $statement->executeQuery()->fetchAllAssociative())) {
            foreach ($heistCasinoDrivers as $keyHeistCasinoDriver => $heistCasinoDriver) {
                $heistCasinoDriverVehicles = explode('+', $heistCasinoDriver['heistCasinoDriverVehicles']);

                $heistCasinoDrivers[$keyHeistCasinoDriver]['heistCasinoDriverVehicles'] = '
                    <tr>
                        <td>' . htmlspecialchars((string)$heistCasinoDriver['name'], ENT_QUOTES) . '</td>
                        <td>' . $this->configurationHelper->formatPercent($heistCasinoDriver['percent'], 2, true) . '</td>
                        <td>
                ';

                $first = true;

                foreach ($heistCasinoDriverVehicles as $heistCasinoDriverVehicle) {
                    if (!$first) {
                        $heistCasinoDrivers[$keyHeistCasinoDriver]['heistCasinoDriverVehicles'] .= ' / ';
                    }

                    $first = false;

                    $heistCasinoDrivers[$keyHeistCasinoDriver]['heistCasinoDriverVehicles'] .= htmlspecialchars($this->vehicleHelper->getNameAndAverageVehicle($heistCasinoDriverVehicle), ENT_QUOTES);
                }

                $heistCasinoDrivers[$keyHeistCasinoDriver]['heistCasinoDriverVehicles'] .= '</td></tr>';
            }
        }

        return $heistCasinoDrivers;
    }

    /**
     * @throws DoctrineDBALException
     */
    public function getHeistCasinoHackers(): array
    {
        $query = "
            SELECT heist_casino_hacker.id, heist_casino_hacker.name, heist_casino_hacker.percent, heist_casino_hacker.time_if_not_detected AS timeIfNotDetected,
                   heist_casino_hacker.time_if_detected AS timeIfDetected
            FROM heist_casino_hacker
            GROUP BY heist_casino_hacker.id
            ORDER BY heist_casino_hacker.percent DESC
        ";

        $statement = $this->entityManager->getConnection()->prepare($query);

        return $statement->executeQuery()->fetchAllAssociative();
    }

    /**
     * @throws DoctrineDBALException
     */
    public function getHeistCasinoMainLoots(): array
    {
        $query = "
            SELECT heist_casino_main_loot.id, heist_casino_main_loot_locale.name, heist_casino_main_loot.price
            FROM heist_casino_main_loot
            LEFT JOIN heist_casino_main_loot_locale
                ON heist_casino_main_loot_locale.heist_casino_main_loot_id = heist_casino_main_loot.id
                AND heist_casino_main_loot_locale.locale_id = :localeId
            ORDER BY heist_casino_main_loot.price DESC
        ";

        $statement = $this->entityManager->getConnection()->prepare($query);
        $statement->bindValue('localeId', $this->security->getUser()->getLocale()->id);

        return $statement->executeQuery()->fetchAllAssociative();
    }

    /**
     * @throws DoctrineDBALException
     */
    public function getHeistCasinoGunmen(): array
    {
        $query = "
            SELECT heist_casino_gunman.id, heist_casino_gunman.name, heist_casino_gunman.percent,
                   GROUP_CONCAT(DISTINCT CONCAT(heist_casino_gunman_weapon.weapon_id, ':', heist_casino_gunman_weapon.approach)
                   ORDER BY CASE heist_casino_gunman_weapon.approach WHEN :approachCunning THEN 1 WHEN :approachStealthy THEN 2 WHEN :approachBrutal THEN 3 ELSE 99 END,
                            heist_casino_gunman_weapon.position SEPARATOR '+') AS heistCasinoGunmanWeapons
            FROM heist_casino_gunman
            LEFT JOIN heist_casino_gunman_weapon ON heist_casino_gunman_weapon.heist_casino_gunman_id = heist_casino_gunman.id
            GROUP BY heist_casino_gunman.id
            ORDER BY heist_casino_gunman.percent DESC
        ";

        $statement = $this->entityManager->getConnection()->prepare($query);
        $statement->bindValue('approachCunning', HeistCasinoApproach::CUNNING->value);
        $statement->bindValue('approachStealthy', HeistCasinoApproach::STEALTHY->value);
        $statement->bindValue('approachBrutal', HeistCasinoApproach::BRUTAL->value);

        if (!empty($heistCasinoGunmen = $statement->executeQuery()->fetchAllAssociative())) {
            foreach ($heistCasinoGunmen as $keyHeistCasinoGunman => $heistCasinoGunman) {
                $heistCasinoGunmanWeapons = explode('+', $heistCasinoGunman['heistCasinoGunmanWeapons']);

                $heistCasinoGunmanWeapon         = explode(':', $heistCasinoGunmanWeapons[0]);
                $heistCasinoGunmanWeaponApproach = $heistCasinoGunmanWeapon[1];

                $heistCasinoGunmen[$keyHeistCasinoGunman]['heistCasinoGunmanWeapons'] = '
                    <tr>
                        <td rowspan="' . count($this->getApproaches()) . '">' . htmlspecialchars((string)$heistCasinoGunman['name'], ENT_QUOTES) . '</td>
                        <td rowspan="' . count($this->getApproaches()) . '">' . $this->configurationHelper->formatPercent($heistCasinoGunman['percent'], 2, true) . '</td>
                ';

                $first = true;

                foreach ($heistCasinoGunmanWeapons as $keyHeistCasinoGunmanWeapon => $heistCasinoGunmanWeapon) {
                    $heistCasinoGunmanWeapon         = explode(':', $heistCasinoGunmanWeapons[$keyHeistCasinoGunmanWeapon]);
                    $heistCasinoGunmanWeaponWeaponId = $heistCasinoGunmanWeapon[0];

                    if ($heistCasinoGunmanWeaponApproach != $heistCasinoGunmanWeapon[1]) {
                        $heistCasinoGunmanWeaponApproach = $heistCasinoGunmanWeapon[1];

                        $heistCasinoGunmen[$keyHeistCasinoGunman]['heistCasinoGunmanWeapons'] .= '
                            </td></tr><tr></td><td>' . $this->getApproachLabel($heistCasinoGunmanWeaponApproach) . '</td><td>
                        ';
                    } else {
                        if (!$first) {
                            $heistCasinoGunmen[$keyHeistCasinoGunman]['heistCasinoGunmanWeapons'] .= ' / ';
                        } else {
                            $heistCasinoGunmen[$keyHeistCasinoGunman]['heistCasinoGunmanWeapons'] .= '
                                <td>' . $this->getApproachLabel($heistCasinoGunmanWeaponApproach) . '</td><td>
                            ';
                        }
                    }

                    $first = false;

                    $heistCasinoGunmen[$keyHeistCasinoGunman]['heistCasinoGunmanWeapons'] .= htmlspecialchars($this->weaponHelper->getNameAndAverageWeapon($heistCasinoGunmanWeaponWeaponId), ENT_QUOTES);
                }
            }
        }

        return $heistCasinoGunmen;
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
            'approach'           => HeistCasinoApproach::tryFrom($value) !== null,
            'difficulty'         => HeistCasinoDifficulty::tryFrom($value) !== null,
            'driver_id', 'gunman_id', 'hacker_id', 'main_loot_id' => (bool)$this->entityManager->getConnection()->fetchOne(
                'SELECT 1 FROM heist_casino_' . substr($place, 0, -3) . ' WHERE id = ?', [$value]
            ),
            'first_time_in_week' => in_array($value, ['0', '1'], true),
            default              => false
        };
    }

    public function optimizeProfits(HeistCasino $heistCasino): array
    {
        if ($heistCasino->getDifficulty() == HeistCasinoDifficulty::NORMAL) {
            $amountEliteChallenge = 50000;
            $percentDifficulty    = 1;
        } elseif ($heistCasino->getDifficulty() == HeistCasinoDifficulty::HARD) {
            $amountEliteChallenge = 100000;
            $percentDifficulty    = 1.1;
        } else {
            $amountEliteChallenge = 0;
            $percentDifficulty    = 1;
        }

        if ($heistCasino->isFirstTimeInWeek() !== null && !$heistCasino->isFirstTimeInWeek()) {
            $percentDifficulty /= 4;
        }

        return [
            'amountEliteChallenge' => $amountEliteChallenge,
            'total'                => $heistCasino->getMainLoot()?->getPrice() * $percentDifficulty
        ];
    }
}
