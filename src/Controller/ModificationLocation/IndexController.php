<?php

namespace App\Controller\ModificationLocation;

use App\Entity\ModificationLocation;
use App\Helper\ConfigurationHelper;
use App\Helper\ModificationLocationHelper;
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
    #[Route(path: '/modification-locations', name: 'modification-locations')]
    #[IsGranted(new Expression('is_granted("ROLE_USER")'))]
    public function index(ConfigurationHelper $configurationHelper, ModificationLocationHelper $modificationLocationHelper): Response
    {
        if (!$this->isGranted(ModificationLocation::PERMISSION_MODIFICATION_LOCATION_VIEW)) {
            throw new AccessDeniedHttpException(ucfirst($configurationHelper->getTranslateLocale("you_are_not_allowed_to_access_this_page")));
        }

        $filter = $modificationLocationHelper->getFilter();

        return $this->render(
            'ModificationLocation/index.html.twig',
            [
                'filter'                => $filter,
                'modificationLocations' => $modificationLocationHelper->getModificationLocations($filter),
                'totalNames'            => $modificationLocationHelper->getTotalNames()
            ]
        );
    }
}
