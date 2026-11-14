<?php

namespace App\Command;

use App\Entity\Configuration;
use App\Entity\PropertyAcquisition;
use App\Entity\PropertyPropertyAcquisition;
use App\Entity\VehicleAcquisition;
use App\Entity\VehicleVehicleAcquisition;
use App\Entity\WeaponAcquisition;
use App\Entity\WeaponWeaponAcquisition;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name       : 'RemovePromotionsCommand',
    description: 'Remove promotions from properties, vehicles and weapons',
    aliases    : ['promotions:remove']
)]
class RemovePromotionsCommand extends Command
{
    const array TYPES = [
        'properties' => [PropertyAcquisition::class, 'propertyAcquisition', PropertyPropertyAcquisition::class, 'property'],
        'vehicles'   => [VehicleAcquisition::class, 'vehicleAcquisition', VehicleVehicleAcquisition::class, 'vehicle'],
        'weapons'    => [WeaponAcquisition::class, 'weaponAcquisition', WeaponWeaponAcquisition::class, 'weapon'],
    ];

    public function __construct(private readonly EntityManagerInterface $entityManager)
    {
        parent::__construct(AsCommand::class);
    }

    /**
     * @throws Exception
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $configuration = $this->entityManager->getRepository(Configuration::class)->find(1);

        $promotionLinksByType = [];

        foreach (self::TYPES as $label => [$acquisitionClass, $acquisitionField, $linkClass]) {
            if (empty($promotionAcquisitions = $this->entityManager->getRepository($acquisitionClass)->findBy(['promotion' => true]))) {
                throw new Exception(sprintf('Unable to find a promotion acquisition for %s', $label));
            }

            $promotionLinksByType[$label] = $this->entityManager->getRepository($linkClass)->findBy([$acquisitionField => $promotionAcquisitions]);

            $output->writeln(sprintf('%s: %d discounted item(s)', ucfirst($label), count($promotionLinksByType[$label])));
        }

        $numberDatas = array_sum(array_map(count(...), $promotionLinksByType));
        $i           = 1;

        $progressBar = new ProgressBar($output, $numberDatas);
        $progressBar->setFormat('debug');

        foreach ($promotionLinksByType as $label => $promotionLinks) {
            [, , $linkClass, $ownerField] = self::TYPES[$label];

            foreach ($promotionLinks as $promotionLink) {
                $owner = $promotionLink->{'get' . ucfirst($ownerField)}();

                $this->entityManager->remove($promotionLink);
                $owner->setDiscountedPercentage(null);

                $this->entityManager->persist($owner);
                $this->entityManager->flush();

                $position = 1;

                foreach ($this->entityManager->getRepository($linkClass)->findBy([$ownerField => $owner], ['position' => 'ASC']) as $remainingLink) {
                    $remainingLink->setPosition($position);

                    $this->entityManager->persist($remainingLink);

                    $position++;
                }

                if ($numberDatas > 0 && round(($i / $numberDatas) * 100) > 0) {
                    $configuration->setPercentagePromotions(round(($i / $numberDatas) * 100));
                }

                $this->entityManager->persist($configuration);
                $this->entityManager->flush();

                $i++;

                $progressBar->advance();
            }
        }

        $progressBar->finish();

        $configuration->setPercentagePromotions(0);

        $this->entityManager->persist($configuration);
        $this->entityManager->flush();

        $output->writeln('');

        return Command::SUCCESS;
    }
}
