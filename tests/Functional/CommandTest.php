<?php

namespace App\Tests\Functional;

use DateTime;
use Doctrine\DBAL\Exception as DoctrineDBALException;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * Commandes planifiées sans accès externe (les imports gtabase.com ne sont pas lancés)
 */
class CommandTest extends AppWebTestCase
{
    /**
     * @throws DoctrineDBALException
     */
    public function testPromotionsAreRemovedAndPositionsRenumbered(): void
    {
        $vehicleId     = (int)$this->fetchOne('SELECT MIN(id) FROM vehicle');
        $promotionId   = (int)$this->fetchOne('SELECT MIN(id) FROM vehicle_acquisition WHERE promotion = 1');
        $acquisitionId = (int)$this->fetchOne('SELECT MIN(id) FROM vehicle_acquisition WHERE promotion = 0');

        $this->connection->executeStatement('DELETE FROM vehicle_vehicle_acquisition WHERE vehicle_id = ?', [$vehicleId]);
        $this->connection->insert('vehicle_vehicle_acquisition', ['position' => 1, 'vehicle_acquisition_id' => $promotionId, 'vehicle_id' => $vehicleId]);
        $this->connection->insert('vehicle_vehicle_acquisition', ['position' => 2, 'vehicle_acquisition_id' => $acquisitionId, 'vehicle_id' => $vehicleId]);
        $this->connection->executeStatement('UPDATE vehicle SET discounted_percentage = 30 WHERE id = ?', [$vehicleId]);

        $this->assertSame(Command::SUCCESS, $this->runCommand('promotions:remove'));

        $this->assertSame(
            [['position' => 1, 'vehicle_acquisition_id' => $acquisitionId]],
            array_map(
                fn(array $row) => array_map(intval(...), $row),
                $this->connection->fetchAllAssociative('SELECT position, vehicle_acquisition_id FROM vehicle_vehicle_acquisition WHERE vehicle_id = ?', [$vehicleId])
            )
        );
        $this->assertNull($this->fetchOne('SELECT discounted_percentage FROM vehicle WHERE id = ?', [$vehicleId]));
        $this->assertSame(0, (int)$this->fetchOne('SELECT COUNT(*) FROM vehicle_vehicle_acquisition WHERE vehicle_acquisition_id = ?', [$promotionId]));
        $this->assertSame(0, (int)$this->fetchOne('SELECT COUNT(*) FROM weapon_weapon_acquisition INNER JOIN weapon_acquisition ON weapon_acquisition.id = weapon_weapon_acquisition.weapon_acquisition_id WHERE weapon_acquisition.promotion = 1'));
        $this->assertSame(0, (int)$this->fetchOne('SELECT COUNT(*) FROM property_property_acquisition INNER JOIN property_acquisition ON property_acquisition.id = property_property_acquisition.property_acquisition_id WHERE property_acquisition.promotion = 1'));
        $this->assertSame(0, (int)$this->fetchOne('SELECT percentage_promotions FROM configuration WHERE id = 1'));
    }

    /**
     * @throws DoctrineDBALException
     */
    public function testUserStatisticSavesThePreviousHourOnce(): void
    {
        $period = new DateTime('- 1 hour');

        $this->connection->executeStatement('DELETE FROM user_statistic WHERE year = ? AND month = ? AND day = ? AND hour = ?', [
            $period->format('Y'), $period->format('m'), $period->format('d'), $period->format('H')
        ]);

        $this->assertSame(Command::SUCCESS, $this->runCommand('statistic:user'));
        $this->assertSame(Command::SUCCESS, $this->runCommand('statistic:user'));

        $this->assertSame(1, (int)$this->fetchOne('SELECT COUNT(*) FROM user_statistic WHERE year = ? AND month = ? AND day = ? AND hour = ?', [
            $period->format('Y'), $period->format('m'), $period->format('d'), $period->format('H')
        ]), 'Relancer la commande met à jour la même heure sans la dupliquer');
    }

    private function runCommand(string $name): int
    {
        $commandTester = new CommandTester(new Application(static::$kernel)->find($name));

        return $commandTester->execute([]);
    }
}
