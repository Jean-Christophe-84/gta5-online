<?php

namespace App\Controller\StorageLocation;

use App\Entity\StorageLocation;
use App\Helper\ConfigurationHelper;
use App\Helper\StorageLocationHelper;
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
    #[Route(path: '/storage-locations', name: 'storage-locations')]
    #[IsGranted(new Expression('is_granted("ROLE_USER")'))]
    public function index(ConfigurationHelper $configurationHelper, StorageLocationHelper $storageLocationHelper): Response
    {
        if (!$this->isGranted(StorageLocation::PERMISSION_STORAGE_LOCATION_VIEW)) {
            throw new AccessDeniedHttpException(ucfirst($configurationHelper->getTranslateLocale("you_are_not_allowed_to_access_this_page")));
        }

        $filter = $storageLocationHelper->getFilter();

        return $this->render(
            'StorageLocation/index.html.twig',
            [
                'filter'           => $filter,
                'storageLocations' => $storageLocationHelper->getStorageLocations($filter),
                'totalNames'       => $storageLocationHelper->getTotalNames()
            ]
        );
    }
}
