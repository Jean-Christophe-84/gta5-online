<?php

namespace App\Controller\PropertyType;

use App\Entity\PropertyType;
use App\Helper\ConfigurationHelper;
use App\Helper\PropertyTypeHelper;
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
    #[Route(path: '/property-types', name: 'property-types')]
    #[IsGranted(new Expression('is_granted("ROLE_USER")'))]
    public function index(ConfigurationHelper $configurationHelper, PropertyTypeHelper $propertyTypeHelper): Response
    {
        if (!$this->isGranted(PropertyType::PERMISSION_PROPERTY_TYPE_VIEW)) {
            throw new AccessDeniedHttpException(ucfirst($configurationHelper->getTranslateLocale("you_are_not_allowed_to_access_this_page")));
        }

        $filter = $propertyTypeHelper->getFilter();

        return $this->render(
            'PropertyType/index.html.twig',
            [
                'filter'                 => $filter,
                'filterStorageLocations' => $propertyTypeHelper->getFilterStorageLocations($filter),
                'propertyTypes'          => $propertyTypeHelper->getPropertyTypes($filter),
                'totalNames'             => $propertyTypeHelper->getTotalNames()
            ]
        );
    }
}
