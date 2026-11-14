<?php

namespace App\Command;

use App\Entity\Configuration;
use App\Entity\CustomizationLocale;
use App\Entity\Locale;
use App\Entity\TitleUpdate;
use App\Entity\Weapon;
use App\Entity\WeaponAcquisitionLocale;
use App\Entity\WeaponClassLocale;
use App\Entity\WeaponCustomization;
use App\Entity\WeaponLocale;
use App\Entity\WeaponManufacturer;
use App\Entity\WeaponWeapon;
use App\Entity\WeaponWeaponAcquisition;
use App\Entity\WeaponWeaponClass;
use App\Helper\ConfigurationHelper;
use App\Helper\WeaponHelper;
use DeepL\DeepLException;
use Doctrine\DBAL\Exception as DoctrineDBALException;
use Doctrine\ORM\EntityManagerInterface;
use DOMDocument;
use DOMXPath;
use Exception;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

#[AsCommand(
    name       : 'GetWeaponsCommand',
    description: 'Get weapons from GTABase',
    aliases    : ['weapons:get']
)]
class GetWeaponsCommand extends Command
{
    public function __construct(
        private readonly ConfigurationHelper   $configurationHelper, private readonly EntityManagerInterface $entityManager,
        private readonly ParameterBagInterface $parameterBag, private readonly WeaponHelper $weaponHelper
    ) {
        parent::__construct(AsCommand::class);
    }

    protected function configure(): void
    {
        $this
            ->addOption("offset", "o", InputOption::VALUE_OPTIONAL, "offset")
            ->addOption("limit", "l", InputOption::VALUE_OPTIONAL, "limit");
    }

    /**
     * @throws DoctrineDBALException|Exception
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $configuration = $this->entityManager->getRepository(Configuration::class)->find(1);
        $urls          = $this->weaponHelper->getUrls();

        $weaponManufacturersDb      = $this->indexByName($this->entityManager->getRepository(WeaponManufacturer::class)->findAll());
        $titleUpdatesDb             = $this->indexByName($this->entityManager->getRepository(TitleUpdate::class)->findAll());
        $weaponAcquisitionLocalesDb = $this->indexByName($this->entityManager->getRepository(WeaponAcquisitionLocale::class)->findAll());
        $customizationsDb           = $this->indexByName($this->entityManager->getRepository(CustomizationLocale::class)->findAll());
        $weaponLocalesDb            = $this->indexByName($this->entityManager->getRepository(WeaponLocale::class)->findAll());
        $weaponClassLocalesDb       = $this->indexByName($this->entityManager->getRepository(WeaponClassLocale::class)->findAll());
        $localesDb                  = $this->entityManager->getRepository(Locale::class)->findAll();
        $localeEnDb                 = $this->entityManager->getRepository(Locale::class)->findOneBy(['code' => 'en']);
        $numberDatas                = count($urls);
        $i                          = 1;

        $progressBar = new ProgressBar($output, $numberDatas);
        $progressBar->setFormat('debug');

        foreach ($urls as $url) {
            $this->processWeaponItem(
                $url, $weaponManufacturersDb, $titleUpdatesDb, $weaponAcquisitionLocalesDb, $customizationsDb, $weaponLocalesDb, $weaponClassLocalesDb,
                $localesDb, $localeEnDb
            );

            if ($numberDatas > 0 && round(($i / $numberDatas) * 100) > 0) {
                $configuration->setPercentageWeapons(round(($i / $numberDatas) * 100));
            }

            $this->entityManager->persist($configuration);
            $this->entityManager->flush();

            $i++;

            $progressBar->advance();
        }

        $progressBar->finish();

        $configuration->setPercentageWeapons(0);

        $this->entityManager->persist($configuration);
        $this->entityManager->flush();

        $output->writeln('');

        return Command::SUCCESS;
    }

    /**
     * @throws Exception
     */
    private function processWeaponItem(
        string $url, array $weaponManufacturersDb, array $titleUpdatesDb, array $weaponAcquisitionLocalesDb, array $customizationsDb, array $weaponLocalesDb,
        array $weaponClassLocalesDb, array $localesDb, Locale $localeEnDb
    ): void {
        $page = file_get_contents($url);

        $document = new DOMDocument();
        @$document->loadHTML($page);

        $xpath = new DOMXPath($document);

        if (!$this->isGtaOnline($xpath)) {
            /** @var Weapon $weapon */
            if ($weapon = $this->getWeapon($xpath, $weaponLocalesDb)) {
                throw new Exception("The following weapon does not exist in GTA 5 Online: " . $this->getWeaponName($xpath) . " (" . $weapon->id . ")");
            }
        } else {
            if (!$weapon = $this->getWeapon($xpath, $weaponLocalesDb)) {
                $weapon = new Weapon();
            }

            $weapon->setWeaponManufacturer($this->getWeaponManufacturer($xpath, $weaponManufacturersDb));
            $weapon->setTitleUpdate($this->getTitleUpdate($xpath, $titleUpdatesDb));
            $weapon->setPath($this->saveImage($xpath, $weapon->id));
            $weapon->setUrl($url);
            $weapon->setRealLifeWeapon($this->getRealLifeWeapon($xpath));
            $weapon->setPrice($this->getPrice($xpath));
            $weapon->setDiscountedPrice($this->getTradePrice($xpath));
            $weapon->setUnlockRank($this->getUnlockRank($xpath));
            $weapon->setEvolution($this->getEvolution($xpath, $localeEnDb));
            $weapon->setDamage($this->getDamage($xpath));
            $weapon->setFireRate($this->getFireRate($xpath));
            $weapon->setAccuracy($this->getAccuracy($xpath));
            $weapon->setRangeWeapon($this->getRangeWeapon($xpath));
            $weapon->setClipSize($this->getClipSize($xpath));

            $this->entityManager->persist($weapon);

            foreach ($localesDb as $localeDb) {
                $weaponLocale = $this->entityManager->getRepository(WeaponLocale::class)->findOneBy(['weapon' => $weapon, 'locale' => $localeDb]);

                if (!$weaponLocale instanceof WeaponLocale) {
                    $weaponLocale = new WeaponLocale();
                    $weaponLocale->setWeapon($weapon);
                    $weaponLocale->setLocale($localeDb);
                    $weaponLocale->setName($this->getWeaponName($xpath));
                }

                $comment      = $this->getComment($xpath, $localeDb);
                $unlockWeapon = $this->getUnlockWeapon($xpath, $localeDb);

                if ($comment || $unlockWeapon) {
                    $weaponLocale->setComment($comment);
                    $weaponLocale->setUnlockWeapon($unlockWeapon);

                    $this->entityManager->persist($weaponLocale);
                }

                $this->entityManager->persist($weaponLocale);
            }

            $weaponWeaponOnesDb         = $this->configurationHelper->indexByEntityId($this->entityManager->getRepository(WeaponWeapon::class)->findBy(['weaponTwo' => $weapon]), 'WeaponOne');
            $weaponWeaponTwosDb         = $this->configurationHelper->indexByEntityId($this->entityManager->getRepository(WeaponWeapon::class)->findBy(['weaponOne' => $weapon]), 'WeaponTwo');
            $weaponCustomizationsDb     = $this->configurationHelper->indexByEntityId($this->entityManager->getRepository(WeaponCustomization::class)->findBy(['weapon' => $weapon]), 'Customization');
            $weaponWeaponAcquisitionsDb = $this->configurationHelper->indexByEntityId($this->entityManager->getRepository(WeaponWeaponAcquisition::class)->findBy(['weapon' => $weapon]), 'WeaponAcquisition');
            $weaponWeaponClassesDb      = $this->configurationHelper->indexByEntityId($this->entityManager->getRepository(WeaponWeaponClass::class)->findBy(['weapon' => $weapon]), 'WeaponClass');

            // Weapon ones
            if (!empty($weaponOnes = $this->getWeaponOnes($xpath, $weaponLocalesDb))) {
                $positionWeaponWeaponOne = 1;

                foreach ($weaponOnes as $weaponOne) {
                    $weaponOneId = $weaponOne->id;

                    if (isset($weaponWeaponOnesDb[$weaponOneId])) {
                        $weaponWeapon = $weaponWeaponOnesDb[$weaponOneId];
                        unset($weaponWeaponOnesDb[$weaponOneId]);
                    } else {
                        $weaponWeapon = new WeaponWeapon();
                        $weaponWeapon->setWeaponOne($weaponOne);
                        $weaponWeapon->setWeaponTwo($weapon);
                    }

                    $weaponWeapon->setPosition($positionWeaponWeaponOne);

                    $this->entityManager->persist($weaponWeapon);

                    $positionWeaponWeaponOne++;
                }
            }

            foreach ($weaponWeaponOnesDb as $weaponWeaponToRemove) {
                $this->entityManager->remove($weaponWeaponToRemove);
            }

            // Weapon twos
            if (!empty($weaponTwos = $this->getWeaponTwos($xpath, $weaponLocalesDb))) {
                $positionWeaponWeaponTwo = 1;

                foreach ($weaponTwos as $weaponTwo) {
                    $weaponTwoId = $weaponTwo->id;

                    if (isset($weaponWeaponTwosDb[$weaponTwoId])) {
                        $weaponWeapon = $weaponWeaponTwosDb[$weaponTwoId];
                        unset($weaponWeaponTwosDb[$weaponTwoId]);
                    } else {
                        $weaponWeapon = new WeaponWeapon();
                        $weaponWeapon->setWeaponOne($weapon);
                        $weaponWeapon->setWeaponTwo($weaponTwo);
                    }

                    $weaponWeapon->setPosition($positionWeaponWeaponTwo);

                    $this->entityManager->persist($weaponWeapon);

                    $positionWeaponWeaponTwo++;
                }
            }

            foreach ($weaponWeaponTwosDb as $weaponWeaponToRemove) {
                $this->entityManager->remove($weaponWeaponToRemove);
            }

            // Customizations
            if (!empty($customizations = $this->getCustomizations($xpath, $customizationsDb))) {
                $positionWeaponCustomization = 1;

                foreach ($customizations as $customization) {
                    $customizationId = $customization->id;

                    if (isset($weaponCustomizationsDb[$customizationId])) {
                        $weaponCustomization = $weaponCustomizationsDb[$customizationId];
                        unset($weaponCustomizationsDb[$customizationId]);
                    } else {
                        $weaponCustomization = new WeaponCustomization();
                        $weaponCustomization->setWeapon($weapon);
                        $weaponCustomization->setCustomization($customization);
                    }

                    $weaponCustomization->setPosition($positionWeaponCustomization);

                    $this->entityManager->persist($weaponCustomization);

                    $positionWeaponCustomization++;
                }
            }

            foreach ($weaponCustomizationsDb as $weaponCustomizationToRemove) {
                $this->entityManager->remove($weaponCustomizationToRemove);
            }

            // Weapon Acquisitions
            if (!empty($weaponAcquisitions = $this->getWeaponAcquisitions($xpath, $weaponAcquisitionLocalesDb))) {
                $positionWeaponWeaponAcquisition = 1;

                foreach ($weaponAcquisitions as $weaponAcquisition) {
                    $weaponAcquisitionId = $weaponAcquisition->id;

                    if (isset($weaponWeaponAcquisitionsDb[$weaponAcquisitionId])) {
                        $weaponWeaponAcquisition = $weaponWeaponAcquisitionsDb[$weaponAcquisitionId];
                        unset($weaponWeaponAcquisitionsDb[$weaponAcquisitionId]);
                    } else {
                        $weaponWeaponAcquisition = new WeaponWeaponAcquisition();
                        $weaponWeaponAcquisition->setWeapon($weapon);
                        $weaponWeaponAcquisition->setWeaponAcquisition($weaponAcquisition);
                    }

                    $weaponWeaponAcquisition->setPosition($positionWeaponWeaponAcquisition);

                    $this->entityManager->persist($weaponWeaponAcquisition);

                    $positionWeaponWeaponAcquisition++;
                }
            }

            foreach ($weaponWeaponAcquisitionsDb as $weaponWeaponAcquisitionToRemove) {
                if (!$weaponWeaponAcquisitionToRemove->getWeaponAcquisition()->isPromotion()) {
                    $this->entityManager->remove($weaponWeaponAcquisitionToRemove);
                }
            }

            // Weapon classes
            if (!empty($weaponClasses = $this->getWeaponClasses($xpath, $weaponClassLocalesDb))) {
                $positionWeaponWeaponClass = 1;

                foreach ($weaponClasses as $weaponClass) {
                    $weaponClassId = $weaponClass->id;

                    if (isset($weaponWeaponClassesDb[$weaponClassId])) {
                        $weaponWeaponClass = $weaponWeaponClassesDb[$weaponClassId];
                        unset($weaponWeaponClassesDb[$weaponClassId]);
                    } else {
                        $weaponWeaponClass = new WeaponWeaponClass();
                        $weaponWeaponClass->setWeapon($weapon);
                        $weaponWeaponClass->setWeaponClass($weaponClass);
                    }

                    $weaponWeaponClass->setPosition($positionWeaponWeaponClass);

                    $this->entityManager->persist($weaponWeaponClass);

                    $positionWeaponWeaponClass++;
                }
            }

            foreach ($weaponWeaponClassesDb as $weaponWeaponClassToRemove) {
                $this->entityManager->remove($weaponWeaponClassToRemove);
            }
        }
    }

    private function findNamedEntity(string $name, array $entitiesByName): ?object
    {
        return $entitiesByName[$name] ?? null;
    }

    private function getAccuracy(DOMXPath $xpath): ?float
    {
        $accuracy = $xpath->query(
            '//li[contains(concat(" ", normalize-space(@class), " "), " accuracy ")]'
            . '//div[contains(concat(" ", normalize-space(@class), " "), " field-value ")]'
        )?->item(0)?->textContent;

        return $accuracy !== null ? round((float)$accuracy, 2) : null;
    }

    private function getClipSize(DOMXPath $xpath): ?float
    {
        $clipSize = $xpath->query(
            '//li[contains(concat(" ", normalize-space(@class), " "), " weapon-capacity ")]'
            . '//div[contains(concat(" ", normalize-space(@class), " "), " field-value ")]'
        )?->item(0)?->textContent;

        return $clipSize !== null ? round((float)$clipSize, 2) : null;
    }

    /**
     * @throws DeepLException
     */
    private function getComment(DOMXPath $xpath, Locale $localeDb): ?string
    {
        if ($commentNode =
            $xpath->query(
                '//li[contains(concat(" ", normalize-space(@class), " "), " notes ")]'
                . '//span[contains(concat(" ", normalize-space(@class), " "), " field-value ")]'
            )?->item(0)
        ) {
            if (str_starts_with(trim($commentNode->textContent), '*')) {
                $comment = substr(trim($commentNode->textContent), 1);
            } else {
                $comment = $commentNode->textContent;
            }
        } else {
            $comment = null;
        }

        if ($comment) {
            if ($localeDb->getCode() != 'en') {
                return $this->configurationHelper->translate($comment, null, $localeDb->getCode());
            }

            return $comment;
        }

        return null;
    }

    /**
     * @throws Exception
     */
    private function getCustomizations(DOMXPath $xpath, array $customizationsDb): array
    {
        $customizations = [];

        $customizationName = $xpath->query(
            '//li[contains(concat(" ", normalize-space(@class), " "), " modifications ")]'
            . '//span[contains(concat(" ", normalize-space(@class), " "), " field-value ")]'
            . '//span[contains(concat(" ", normalize-space(@class), " "), " yes ")]'
        )?->item(0)?->textContent;

        if ($customizationName !== null) {
            $customizationLocale = $this->findNamedEntity($customizationName, $customizationsDb);

            if (!$customizationLocale) {
                throw new Exception(
                    'Unable to find the customization "' . $customizationName . '" for weapon: ' . $this->getWeaponName($xpath)
                );
            }

            $customizations[$customizationLocale->getCustomization()->id] = $customizationLocale->getCustomization();
        }

        return $customizations;
    }

    private function getDamage(DOMXPath $xpath): ?float
    {
        $damage = $xpath->query(
            '//li[contains(concat(" ", normalize-space(@class), " "), " damage ")]'
            . '//div[contains(concat(" ", normalize-space(@class), " "), " field-value ")]'
        )?->item(0)?->textContent;

        return $damage !== null ? round((float)$damage, 2) : null;
    }

    /**
     * @throws DeepLException
     */
    private function getEvolution(DOMXPath $xpath, Locale $localeDb): bool
    {
        $howToUnlock = $this->getUnlockWeapon($xpath, $localeDb);

        return $howToUnlock !== null && str_contains($howToUnlock, 'variant');
    }

    private function getFireRate(DOMXPath $xpath): ?float
    {
        $fireRate = $xpath->query(
            '//li[contains(concat(" ", normalize-space(@class), " "), " fire-rate ")]'
            . '//div[contains(concat(" ", normalize-space(@class), " "), " field-value ")]'
        )?->item(0)?->textContent;

        return $fireRate !== null ? round((float)$fireRate, 2) : null;
    }

    /**
     * @throws Exception
     */
    private function getNode(DOMXPath $xpath, string $nodeName, ?string $separator = null): ?string
    {
        $nodes = $xpath->query('//' . $nodeName);

        if ($separator !== null) {
            if (isset($nodes[0])) {
                $title = explode($separator, $nodes[0]->getAttribute('title') ?? null);

                return $title[1] ?? null;
            }

            return null;
        }

        return $nodes?->item(0)?->textContent;
    }

    private function getPrice(DOMXPath $xpath): ?int
    {
        $price = $xpath->query(
            '//li[contains(concat(" ", normalize-space(@class), " "), " price ")]' .
            '//span[contains(concat(" ", normalize-space(@class), " "), " field-value ")]'
        )?->item(0)?->textContent;

        return $price !== null ? (int)str_replace(['$', ','], ['', ''], $price) : null;
    }

    private function getRangeWeapon(DOMXPath $xpath): ?float
    {
        $range = $xpath->query(
            '//li[contains(concat(" ", normalize-space(@class), " "), " range ")]'
            . '//div[contains(concat(" ", normalize-space(@class), " "), " field-value ")]'
        )?->item(0)?->textContent;

        return $range !== null ? round((float)$range, 2) : null;
    }

    private function getRealLifeWeapon(DOMXPath $xpath): ?string
    {
        return $xpath->query(
            '//li[contains(concat(" ", normalize-space(@class), " "), " based-on ")]'
            . '//span[contains(concat(" ", normalize-space(@class), " "), " field-value ")]'
        )?->item(0)?->textContent;
    }

    private function getThumbnail(DOMXPath $xpath): string
    {
        $source = $xpath->query('//figure[contains(@class, "item-image")]//img[@src]')?->item(0)?->getAttribute('src');

        if (str_contains($source, 'https://www.gtabase.com')) {
            return $source;
        }

        return "https://www.gtabase.com" . $source;
    }

    /**
     * @throws Exception
     */
    private function getTitleUpdate(DOMXPath $xpath, array $titleUpdatesDb): ?TitleUpdate
    {
        if ($titleUpdateName =
            $xpath->query(
                '//li[contains(concat(" ", normalize-space(@class), " "), " update ")]'
                . '//span[contains(concat(" ", normalize-space(@class), " "), " field-value ")]'
                . '//span[contains(concat(" ", normalize-space(@class), " "), " field-value ")]'
            )?->item(0)?->textContent
        ) {
            if (!isset($titleUpdatesDb[$titleUpdateName])) {
                throw new Exception(
                    'Unable to find the title update "' . $titleUpdateName . '" for weapon: ' . $this->getWeaponName($xpath)
                );
            }

            return $titleUpdatesDb[$titleUpdateName];
        }

        return null;
    }

    private function getTradePrice(DOMXPath $xpath): ?int
    {
        $tradePrice = $xpath->query(
            '//li[contains(concat(" ", normalize-space(@class), " "), " trade-price ")]' .
            '//span[contains(concat(" ", normalize-space(@class), " "), " field-value ")]' .
            '/text()'
        )?->item(0)?->textContent;

        return $tradePrice !== null ? (int)str_replace(['$', ','], ['', ''], $tradePrice) : null;
    }

    private function getUnlockRank(DOMXPath $xpath): ?int
    {
        return $xpath->query(
            '//li[contains(concat(" ", normalize-space(@class), " "), " online-rank ")]'
            . '//span[contains(concat(" ", normalize-space(@class), " "), " field-value ")]'
        )?->item(0)?->textContent;
    }

    /**
     * @throws DeepLException
     */
    private function getUnlockWeapon(DOMXPath $xpath, Locale $localeDb): ?string
    {
        $unlockWeapon = $xpath->query(
            '//li[contains(concat(" ", normalize-space(@class), " "), " how-to-unlock ")]'
            . '//span[contains(concat(" ", normalize-space(@class), " "), " field-value ")]'
        )?->item(0)?->textContent;

        if ($unlockWeapon) {
            if ($localeDb->getCode() != 'en') {
                return $this->configurationHelper->translate($unlockWeapon, null, $localeDb->getCode());
            }

            return $unlockWeapon;
        }

        return null;
    }

    /**
     * @throws Exception
     */
    private function getWeapon(DOMXPath $xpath, array $weaponLocalesDb): Weapon|bool|null
    {
        if ($weaponName = $this->getWeaponName($xpath)) {
            $weaponLocale = $this->findNamedEntity($weaponName, $weaponLocalesDb);

            if (!$weaponLocale) {
                return false;
            }

            return $weaponLocale->getWeapon();
        }

        return null;
    }

    /**
     * @throws Exception
     */
    private function getWeaponAcquisitions(DOMXPath $xpath, array $weaponAcquisitionLocalesDb): array
    {
        $weaponAcquisitions = [];

        if (!empty($nodes = $xpath->query('//li[contains(concat(" ", normalize-space(@class), " "), " purchase ")]//a[@title]'))) {
            foreach ($nodes as $node) {
                $weaponAcquisitionName = explode('Acquisition: ', $node->getAttribute('title'));

                $weaponAcquisitionLocale = $this->findNamedEntity($weaponAcquisitionName[1], $weaponAcquisitionLocalesDb);

                if (!$weaponAcquisitionLocale) {
                    throw new Exception(
                        'Unable to find the weapon acquisition "' . $weaponAcquisitionName[1] . '" for weapon: ' . $this->getWeaponName($xpath)
                    );
                }

                $weaponAcquisitions[$weaponAcquisitionLocale->getWeaponAcquisition()->id] = $weaponAcquisitionLocale->getWeaponAcquisition();
            }
        }

        return array_values($weaponAcquisitions);
    }

    /**
     * @throws Exception
     */
    private function getWeaponClasses(DOMXPath $xpath, array $weaponClassLocalesDb): array
    {
        $weaponClasses = [];

        if (!empty($nodes = $xpath->query('//li[contains(concat(" ", normalize-space(@class), " "), " weapon-class ")]//a[@title]'))) {
            foreach ($nodes as $node) {
                $weaponClassName = explode('Weapon Class: ', $node->getAttribute('title'));

                $weaponClassLocale = $this->findNamedEntity($weaponClassName[1], $weaponClassLocalesDb);

                if (!$weaponClassLocale) {
                    throw new Exception(
                        'Unable to find the weapon class "' . $weaponClassName[1] . '" for weapon: ' . $this->getWeaponName($xpath)
                    );
                }

                $weaponClasses[$weaponClassLocale->getWeaponClass()->id] = $weaponClassLocale->getWeaponClass();
            }
        }

        return array_values($weaponClasses);
    }

    /**
     * @throws Exception
     */
    private function getWeaponManufacturer(DOMXPath $xpath, array $weaponManufacturersDb): ?WeaponManufacturer
    {
        if ($weaponManufacturerName = $this->getNode($xpath, 'a[contains(@href, "#attr.ct2.value")]', 'Manufacturer: ')) {
            if (!isset($weaponManufacturersDb[$weaponManufacturerName])) {
                throw new Exception('Unable to find the manufacturer "' . $weaponManufacturerName . '" for weapon: ' . $this->getWeaponName($xpath));
            }

            return $weaponManufacturersDb[$weaponManufacturerName];
        }

        return null;
    }

    /**
     * @throws Exception
     */
    private function getWeaponName(DOMXPath $xpath): string
    {
        $nameWithManufacturerName = $this->getNode($xpath, 'h1');
        $manufacturerName         = $this->getNode($xpath, 'a[contains(@href, "#attr.ct2.value")]', 'Manufacturer: ');

        if ($manufacturerName) {
            $name = explode($manufacturerName . ' ', $nameWithManufacturerName);

            return isset($name[1]) ? trim($name[1]) : trim($name[0]);
        }

        return trim($nameWithManufacturerName);
    }

    /**
     * @throws Exception
     */
    private function getWeaponOnes(DOMXPath $xpath, array $weaponOnesDb): array
    {
        $weaponOnes = [];

        if ($weaponOneNames =
            $xpath->query(
                '//li[contains(concat(" ", normalize-space(@class), " "), " converted-from ")]'
                . '//h3[contains(concat(" ", normalize-space(@class), " "), " contentheading ")]/a'
            )
        ) {
            foreach ($weaponOneNames as $weaponOneName) {
                if ($weaponOne = $this->findNamedEntity(trim($weaponOneName->textContent), $weaponOnesDb)) {
                    $weaponOnes[$weaponOne->getWeapon()->id] = $weaponOne->getWeapon();
                }
            }
        }

        return array_values($weaponOnes);
    }

    /**
     * @throws Exception
     */
    private function getWeaponTwos(DOMXPath $xpath, array $weaponTwosDb): array
    {
        $weaponTwos = [];

        if ($weaponTwoNames =
            $xpath->query(
                '//li[contains(concat(" ", normalize-space(@class), " "), " variant-conversion ")]'
                . '//h3[contains(concat(" ", normalize-space(@class), " "), " contentheading ")]/a'
            )
        ) {
            foreach ($weaponTwoNames as $weaponTwoName) {
                if ($weaponTwo = $this->findNamedEntity(trim($weaponTwoName->textContent), $weaponTwosDb)) {
                    $weaponTwos[$weaponTwo->getWeapon()->id] = $weaponTwo->getWeapon();
                }
            }
        }

        return array_values($weaponTwos);
    }

    private function indexByName(array $entities): array
    {
        $indexedEntities = [];

        foreach ($entities as $entity) {
            $indexedEntities[$entity->getName()] = $entity;
        }

        return $indexedEntities;
    }

    /**
     * @throws Exception
     */
    private function isGtaOnline(DOMXPath $xpath): bool
    {
        return $this->getNode($xpath, 'a[contains(@href, "#attr.ct5.value=gta-online")]') ?? false;
    }

    /**
     * @throws Exception
     */
    private function saveImage(DOMXPath $xpath, int $weaponId): string|null
    {
        $url       = $this->getThumbnail($xpath);
        $localPath =
            $this->parameterBag->get('kernel.project_dir') . DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'weapon';

        if (!$weaponId) {
            throw new Exception('Unable to find the image "' . $url .'" for weapon: ' . $this->getWeaponName($xpath));
        }
        // Générer un nom de fichier unique
        $filename = $weaponId . '.jpg';
        $fullPath = $localPath . '/' . $filename;

        if (!file_exists($fullPath)) {
            // Récupérer le contenu de l'image
            $imageContent = file_get_contents($url);

            if ($imageContent !== false) {
                // Sauvegarder l'image localement
                if (file_put_contents($fullPath, $imageContent) !== false) {
                    return $filename;
                }

                return null;
            }

            return null;
        }

        return $filename;
    }
}
