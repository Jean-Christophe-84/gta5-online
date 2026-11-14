<?php

namespace App\Controller\WeaponAcquisition;

use App\Entity\WeaponAcquisition;
use App\Helper\ConfigurationHelper;
use App\Helper\WeaponAcquisitionHelper;
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
    #[Route(path: '/weapon-acquisitions', name: 'weapon-acquisitions')]
    #[IsGranted(new Expression('is_granted("ROLE_USER")'))]
    public function index(ConfigurationHelper $configurationHelper, WeaponAcquisitionHelper $weaponAcquisitionHelper): Response
    {
        if (!$this->isGranted(WeaponAcquisition::PERMISSION_WEAPON_ACQUISITION_VIEW)) {
            throw new AccessDeniedHttpException(ucfirst($configurationHelper->getTranslateLocale("you_are_not_allowed_to_access_this_page")));
        }

        $filter = $weaponAcquisitionHelper->getFilter();

        return $this->render(
            'WeaponAcquisition/index.html.twig',
            [
                'filter'             => $filter,
                'totalNames'         => $weaponAcquisitionHelper->getTotalNames(),
                'weaponAcquisitions' => $weaponAcquisitionHelper->getWeaponAcquisitions($filter)
            ]
        );
    }
}
