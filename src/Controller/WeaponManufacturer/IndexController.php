<?php

namespace App\Controller\WeaponManufacturer;

use App\Entity\WeaponManufacturer;
use App\Helper\ConfigurationHelper;
use App\Helper\WeaponManufacturerHelper;
use Doctrine\DBAL\Exception as DoctrineDBALException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\ExpressionLanguage\Expression;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class IndexController extends AbstractController
{
    /**
     * @throws DoctrineDBALException
     */
    #[Route(path: '/weapon-manufacturers', name: 'weapon-manufacturers')]
    #[IsGranted(new Expression('is_granted("ROLE_USER")'))]
    public function index(ConfigurationHelper $configurationHelper, WeaponManufacturerHelper $weaponManufacturerHelper): Response
    {
        if (!$this->isGranted(WeaponManufacturer::PERMISSION_WEAPON_MANUFACTURER_VIEW)) {
            throw new AccessDeniedHttpException(ucfirst($configurationHelper->getTranslateLocale("you_are_not_allowed_to_access_this_page")));
        }

        $filter = $weaponManufacturerHelper->getFilter();

        return $this->render(
            'WeaponManufacturer/index.html.twig',
            [
                'filter'              => $filter,
                'totalNames'          => $weaponManufacturerHelper->getTotalNames(),
                'weaponManufacturers' => $weaponManufacturerHelper->getWeaponManufacturers($filter)
            ]
        );
    }
}
