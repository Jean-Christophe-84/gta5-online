<?php

namespace App\Command;

use App\Entity\Configuration;
use App\Entity\Locale;
use App\Entity\Property;
use App\Entity\PropertyAcquisitionLocale;
use App\Entity\PropertyArea;
use App\Entity\PropertyLocale;
use App\Entity\PropertyLocation;
use App\Entity\PropertyPropertyAcquisition;
use App\Entity\PropertyType;
use App\Entity\PropertyTypeLocale;
use App\Entity\TitleUpdate;
use App\Helper\ConfigurationHelper;
use App\Helper\PropertyHelper;
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
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

#[AsCommand(
    name       : 'GetPropertiesCommand',
    description: 'Get properties from GTABase',
    aliases    : ['properties:get']
)]
class GetPropertiesCommand extends Command
{
    public function __construct(
        private readonly ConfigurationHelper   $configurationHelper, private readonly EntityManagerInterface $entityManager,
        private readonly ParameterBagInterface $parameterBag, private readonly PropertyHelper $propertyHelper
    ) {
        parent::__construct(AsCommand::class);
    }

    /**
     * @throws DoctrineDBALException|Exception
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $configuration = $this->entityManager->getRepository(Configuration::class)->find(1);
        $urls          = $this->propertyHelper->getUrls();

        $propertyTypeLocalesDb        = $this->indexByName($this->entityManager->getRepository(PropertyTypeLocale::class)->findAll());
        $propertyAreasDb              = $this->indexByName($this->entityManager->getRepository(PropertyArea::class)->findAll());
        $propertyLocationsDb          = $this->indexByName($this->entityManager->getRepository(PropertyLocation::class)->findAll());
        $titleUpdatesDb               = $this->indexByName($this->entityManager->getRepository(TitleUpdate::class)->findAll());
        $propertyAcquisitionLocalesDb = $this->indexByName($this->entityManager->getRepository(PropertyAcquisitionLocale::class)->findAll());
        $propertiesDb                 = $this->indexByName($this->entityManager->getRepository(Property::class)->findAll());
        $localesDb                    = $this->entityManager->getRepository(Locale::class)->findAll();
        $numberDatas                  = count($urls);
        $i                            = 1;

        $progressBar = new ProgressBar($output, $numberDatas);
        $progressBar->setFormat('debug');

        foreach ($urls as $url) {
            $this->processPropertyItem(
                $url, $propertyTypeLocalesDb, $propertyAreasDb, $propertyLocationsDb, $titleUpdatesDb, $propertyAcquisitionLocalesDb, $propertiesDb, $localesDb
            );

            if ($numberDatas > 0 && round(($i / $numberDatas) * 100) > 0) {
                $configuration->setPercentageProperties(round(($i / $numberDatas) * 100));
            }

            $this->entityManager->persist($configuration);
            $this->entityManager->flush();

            $i++;

            $progressBar->advance();
        }

        $progressBar->finish();

        $configuration->setPercentageProperties(0);

        $this->entityManager->persist($configuration);
        $this->entityManager->flush();

        $output->writeln('');

        return Command::SUCCESS;
    }

    /**
     * @throws Exception
     */
    private function processPropertyItem(
        string $url, array $propertyTypeLocalesDb, array $propertyAreasDb, array $propertyLocationsDb, array $titleUpdatesDb,
        array $propertyAcquisitionLocalesDb, array $propertiesDb, array $localesDb
    ): void {
        $page = file_get_contents($url);

        $document = new DOMDocument();
        @$document->loadHTML($page);

        $xpath = new DOMXPath($document);

        if (!$property = $this->getProperty($xpath, $propertiesDb)) {
            $property = new Property();
            $property->setName($this->getPropertyName($xpath));
        }

        if ($property->getPropertyType() instanceof PropertyType) {
            $property->setPropertyType($this->getPropertyType($xpath, $propertyTypeLocalesDb));
        }

        $property->setPropertyArea($this->getPropertyArea($xpath, $propertyAreasDb));
        $property->setPropertyLocation($this->getPropertyLocation($xpath, $propertyLocationsDb, $propertyAreasDb));
        $property->setTitleUpdate($this->getTitleUpdate($xpath, $titleUpdatesDb));
        $property->setPath($this->saveImage($xpath, $property->id));
        $property->setUrl($url);

        if (($price = $this->getPrice($xpath)) !== null) {
            $property->setPrice($price);
        } elseif ($property->id === null) {
            $property->setPrice(0);
        }

        $property->setDiscountedPrice($this->getTradePrice($xpath));

        $this->entityManager->persist($property);

        foreach ($localesDb as $localeDb) {
            if ($comment = $this->getComment($xpath, $localeDb)) {
                $propertyLocale = new PropertyLocale();
                $propertyLocale->setProperty($property);
                $propertyLocale->setLocale($localeDb);
                $propertyLocale->setComment($comment);

                $this->entityManager->persist($propertyLocale);
            }
        }

        $propertyPropertyAcquisitionsDb = $this->configurationHelper->indexByEntityId($this->entityManager->getRepository(PropertyPropertyAcquisition::class)->findBy(['property' => $property]), 'PropertyAcquisition');

        // Property Acquisitions
        if (!empty($propertyAcquisitions = $this->getPropertyAcquisitions($xpath, $propertyAcquisitionLocalesDb))) {
            $positionPropertyPropertyAcquisition = 1;

            foreach ($propertyAcquisitions as $propertyAcquisition) {
                $propertyAcquisitionId = $propertyAcquisition->id;

                if (isset($propertyPropertyAcquisitionsDb[$propertyAcquisitionId])) {
                    $propertyPropertyAcquisition = $propertyPropertyAcquisitionsDb[$propertyAcquisitionId];
                    unset($propertyPropertyAcquisitionsDb[$propertyAcquisitionId]);
                } else {
                    $propertyPropertyAcquisition = new PropertyPropertyAcquisition();
                    $propertyPropertyAcquisition->setProperty($property);
                    $propertyPropertyAcquisition->setPropertyAcquisition($propertyAcquisition);
                }

                $propertyPropertyAcquisition->setPosition($positionPropertyPropertyAcquisition);

                $this->entityManager->persist($propertyPropertyAcquisition);

                $positionPropertyPropertyAcquisition++;
            }

            foreach ($propertyPropertyAcquisitionsDb as $propertyPropertyAcquisitionToRemove) {
                if (!$propertyPropertyAcquisitionToRemove->getPropertyAcquisition()->isPromotion()) {
                    $this->entityManager->remove($propertyPropertyAcquisitionToRemove);
                }
            }
        }
    }

    private function findNamedEntity(string $name, array $entitiesByName): ?object
    {
        return $entitiesByName[$name] ?? null;
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

        $price = $price !== null ? str_replace(['$', ','], ['', ''], trim($price)) : '';

        return is_numeric($price[0] ?? '') ? (int)$price : null;
    }

    /**
     * @throws Exception
     */
    private function getProperty(DOMXPath $xpath, array $propertiesDb): Property|bool|null
    {
        if ($propertyName = $this->getPropertyName($xpath)) {
            if (!isset($propertiesDb[$propertyName])) {
                return false;
            }

            return $propertiesDb[$propertyName];
        }

        return null;
    }

    /**
     * @throws Exception
     */
    private function getPropertyAcquisitions(DOMXPath $xpath, array $propertyAcquisitionLocalesDb): array
    {
        $propertyAcquisitions = [];

        if (!empty($nodes = $xpath->query('//li[contains(concat(" ", normalize-space(@class), " "), " purchase ")]//a[@title]'))) {
            foreach ($nodes as $node) {
                $propertyAcquisitionName = explode('Acquisition: ', $node->getAttribute('title'));

                $propertyAcquisitionLocale = $this->findNamedEntity($propertyAcquisitionName[1], $propertyAcquisitionLocalesDb);

                if (!$propertyAcquisitionLocale) {
                    throw new Exception(
                        'Unable to find the property acquisition "' . $propertyAcquisitionName[1] . '" for property: ' . $this->getPropertyName($xpath)
                    );
                }

                $propertyAcquisitions[$propertyAcquisitionLocale->getPropertyAcquisition()->id] = $propertyAcquisitionLocale->getPropertyAcquisition();
            }
        }

        return array_values($propertyAcquisitions);
    }

    /**
     * @throws Exception
     */
    private function getPropertyArea(DOMXPath $xpath, array $propertyAreaLocalesDb): ?PropertyArea
    {
        if ($propertyAreaName =
            $xpath->query(
                '//li[contains(concat(" ", normalize-space(@class), " "), " area ")]'
                . '//span[contains(concat(" ", normalize-space(@class), " "), " field-value ")]'
            )?->item(0)?->textContent
        ) {
            if (isset($propertyAreaLocalesDb[$propertyAreaName])) {
                return $propertyAreaLocalesDb[$propertyAreaName];
            }

            throw new Exception('Unable to find the property area "' . $propertyAreaName . '" for property: ' . $this->getPropertyName($xpath));
        }

        return null;
    }

    /**
     * @throws Exception
     */
    private function getPropertyLocation(DOMXPath $xpath, array $propertyLocationLocalesDb, array $propertyAreaLocalesDb): ?PropertyLocation
    {
        if ($propertyLocationName =
            $xpath->query(
                '//li[contains(concat(" ", normalize-space(@class), " "), " location ")]'
                . '//span[contains(concat(" ", normalize-space(@class), " "), " field-value ")]'
            )?->item(0)?->textContent
        ) {
            if (isset($propertyLocationLocalesDb[$propertyLocationName])) {
                return $propertyLocationLocalesDb[$propertyLocationName];
            }

            $propertyLocation = new PropertyLocation();
            $propertyLocation->setPropertyArea($this->getPropertyArea($xpath, $propertyAreaLocalesDb));
            $propertyLocation->setName($propertyLocationName);
            $this->entityManager->persist($propertyLocation);
            $this->entityManager->flush();
            return $propertyLocation;
        }

        return null;
    }

    /**
     * @throws Exception
     */
    private function getPropertyName(DOMXPath $xpath): string
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
    private function getPropertyType(DOMXPath $xpath, array $propertyTypeLocalesDb): ?PropertyType
    {
        if ($propertyTypeName =
            $xpath->query(
                '//li[contains(concat(" ", normalize-space(@class), " "), " property ")]'
                . '//span[contains(concat(" ", normalize-space(@class), " "), " field-value ")]'
            )?->item(0)?->textContent
        ) {
            if (isset($propertyTypeLocalesDb[$propertyTypeName])) {
                return $propertyTypeLocalesDb[$propertyTypeName]->getPropertyType();
            }

            throw new Exception('Unable to find the property type "' . $propertyTypeName . '" for property: ' . $this->getPropertyName($xpath));
        }

        return null;
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
                throw new Exception('Unable to find the title update "' . $titleUpdateName . '" for property: ' . $this->getPropertyName($xpath));
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
    private function saveImage(DOMXPath $xpath, ?int $propertyId): string|null
    {
        $url       = $this->getThumbnail($xpath);
        $localPath =
            $this->parameterBag->get('kernel.project_dir') . DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR .
            'property';

        if (!$propertyId) {
            throw new Exception('Unable to find the image "' . $url . '" for property: ' . $this->getPropertyName($xpath));
        }
        // Générer un nom de fichier unique
        $filename = $propertyId . '.jpg';
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
