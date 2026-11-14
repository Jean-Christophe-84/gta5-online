<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\Traits\InfoUpdateTrait;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
class BusinessGood
{
    const string PERMISSION_BUSINESS_GOOD_VIEW   = 'BUSINESS_GOOD_VIEW';
    const string PERMISSION_BUSINESS_GOOD_ADD    = 'BUSINESS_GOOD_ADD';
    const string PERMISSION_BUSINESS_GOOD_EDIT   = 'BUSINESS_GOOD_EDIT';
    const string PERMISSION_BUSINESS_GOOD_DELETE = 'BUSINESS_GOOD_DELETE';

    #[ORM\Column(nullable: false)]
    #[ORM\GeneratedValue]
    #[ORM\Id]
    public ?int $id = null {
        get {
            return $this->id;
        }
    }

    #[ORM\JoinColumn(nullable: false)]
    #[ORM\ManyToOne(inversedBy: 'businessGoods')]
    private Business $business;

    #[ORM\Column(nullable: true)]
    private ?int $stockDealer = null;

    #[ORM\Column(nullable: true)]
    private ?int $stockDealerTotal = null;

    #[ORM\Column(nullable: true)]
    private ?int $costOfRestock = null;

    #[ORM\Column(nullable: true)]
    private ?float $replenishmentRate = null;

    #[ORM\Column(nullable: true)]
    private ?float $replenishmentRateWithUpgradedEquipment = null;

    #[ORM\Column(nullable: true)]
    private ?float $replenishmentRateWithUpgradedStaff = null;

    #[ORM\Column(nullable: true)]
    private ?float $replenishmentRateWithUpgradedEquipmentAndStaff = null;

    #[ORM\Column(nullable: true)]
    private ?int $sale = null;

    #[ORM\Column(nullable: true)]
    private ?int $saleWithUpgradedEquipment = null;

    #[ORM\Column(nullable: true)]
    private ?int $saleWithUpgradedStaff = null;

    #[ORM\Column(nullable: true)]
    private ?int $saleWithUpgradedEquipmentAndStaff = null;

    #[ORM\Column(nullable: true)]
    private ?int $timeForRestock = null;

    #[ORM\Column(nullable: true)]
    private ?int $timeForRestockWithUpgradedEquipment = null;

    #[ORM\Column(nullable: true)]
    private ?int $timeForRestockWithUpgradedStaff = null;

    #[ORM\Column(nullable: true)]
    private ?int $timeForRestockWithUpgradedEquipmentAndStaff = null;

    #[ORM\OneToMany(targetEntity: BusinessGoodLocale::class, mappedBy: 'businessGood')]
    public Collection $businessGoodLocales {
        get {
            return $this->businessGoodLocales;
        }
    }

    use InfoUpdateTrait;

    public function __construct()
    {
        $this->businessGoodLocales = new ArrayCollection();
    }

    public function getBusiness(): ?Business
    {
        return $this->business;
    }

    public function setBusiness(Business $business): static
    {
        $this->business = $business;

        return $this;
    }

    public function getStockDealer(): ?int
    {
        return $this->stockDealer;
    }

    public function setStockDealer(?int $stockDealer): static
    {
        $this->stockDealer = $stockDealer;

        return $this;
    }

    public function getStockDealerTotal(): ?int
    {
        return $this->stockDealerTotal;
    }

    public function setStockDealerTotal(?int $stockDealerTotal): static
    {
        $this->stockDealerTotal = $stockDealerTotal;

        return $this;
    }

    public function getCostOfRestock(): ?int
    {
        return $this->costOfRestock;
    }

    public function setCostOfRestock(?int $costOfRestock): static
    {
        $this->costOfRestock = $costOfRestock;

        return $this;
    }

    public function getReplenishmentRate(): ?float
    {
        return $this->replenishmentRate;
    }

    public function setReplenishmentRate(?float $replenishmentRate): static
    {
        $this->replenishmentRate = $replenishmentRate;

        return $this;
    }

    public function getReplenishmentRateWithUpgradedEquipment(): ?float
    {
        return $this->replenishmentRateWithUpgradedEquipment;
    }

    public function setReplenishmentRateWithUpgradedEquipment(?float $replenishmentRateWithUpgradedEquipment): static
    {
        $this->replenishmentRateWithUpgradedEquipment = $replenishmentRateWithUpgradedEquipment;

        return $this;
    }

    public function getReplenishmentRateWithUpgradedStaff(): ?float
    {
        return $this->replenishmentRateWithUpgradedStaff;
    }

    public function setReplenishmentRateWithUpgradedStaff(?float $replenishmentRateWithUpgradedStaff): static
    {
        $this->replenishmentRateWithUpgradedStaff = $replenishmentRateWithUpgradedStaff;

        return $this;
    }

    public function getReplenishmentRateWithUpgradedEquipmentAndStaff(): ?float
    {
        return $this->replenishmentRateWithUpgradedEquipmentAndStaff;
    }

    public function setReplenishmentRateWithUpgradedEquipmentAndStaff(?float $replenishmentRateWithUpgradedEquipmentAndStaff): static
    {
        $this->replenishmentRateWithUpgradedEquipmentAndStaff = $replenishmentRateWithUpgradedEquipmentAndStaff;

        return $this;
    }

    public function getSale(): ?int
    {
        return $this->sale;
    }

    public function setSale(?int $sale): static
    {
        $this->sale = $sale;

        return $this;
    }

    public function getSaleWithUpgradedEquipment(): ?int
    {
        return $this->saleWithUpgradedEquipment;
    }

    public function setSaleWithUpgradedEquipment(?int $saleWithUpgradedEquipment): static
    {
        $this->saleWithUpgradedEquipment = $saleWithUpgradedEquipment;

        return $this;
    }

    public function getSaleWithUpgradedStaff(): ?int
    {
        return $this->saleWithUpgradedStaff;
    }

    public function setSaleWithUpgradedStaff(?int $saleWithUpgradedStaff): static
    {
        $this->saleWithUpgradedStaff = $saleWithUpgradedStaff;

        return $this;
    }

    public function getSaleWithUpgradedEquipmentAndStaff(): ?int
    {
        return $this->saleWithUpgradedEquipmentAndStaff;
    }

    public function setSaleWithUpgradedEquipmentAndStaff(?int $saleWithUpgradedEquipmentAndStaff): static
    {
        $this->saleWithUpgradedEquipmentAndStaff = $saleWithUpgradedEquipmentAndStaff;

        return $this;
    }

    public function getTimeForRestock(): ?int
    {
        return $this->timeForRestock;
    }

    public function setTimeForRestock(?int $timeForRestock): static
    {
        $this->timeForRestock = $timeForRestock;

        return $this;
    }

    public function getTimeForRestockWithUpgradedEquipment(): ?int
    {
        return $this->timeForRestockWithUpgradedEquipment;
    }

    public function setTimeForRestockWithUpgradedEquipment(?int $timeForRestockWithUpgradedEquipment): static
    {
        $this->timeForRestockWithUpgradedEquipment = $timeForRestockWithUpgradedEquipment;

        return $this;
    }

    public function getTimeForRestockWithUpgradedStaff(): ?int
    {
        return $this->timeForRestockWithUpgradedStaff;
    }

    public function setTimeForRestockWithUpgradedStaff(?int $timeForRestockWithUpgradedStaff): static
    {
        $this->timeForRestockWithUpgradedStaff = $timeForRestockWithUpgradedStaff;

        return $this;
    }

    public function getTimeForRestockWithUpgradedEquipmentAndStaff(): ?int
    {
        return $this->timeForRestockWithUpgradedEquipmentAndStaff;
    }

    public function setTimeForRestockWithUpgradedEquipmentAndStaff(?int $timeForRestockWithUpgradedEquipmentAndStaff): static
    {
        $this->timeForRestockWithUpgradedEquipmentAndStaff = $timeForRestockWithUpgradedEquipmentAndStaff;

        return $this;
    }
}
