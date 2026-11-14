<?php

namespace App\Controller\PropertyAcquisition;

use App\Entity\PropertyAcquisition;
use App\Helper\ConfigurationHelper;
use App\Helper\PropertyAcquisitionHelper;
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
    #[Route(path: '/property-acquisitions', name: 'property-acquisitions')]
    #[IsGranted(new Expression('is_granted("ROLE_USER")'))]
    public function index(ConfigurationHelper $configurationHelper, PropertyAcquisitionHelper $propertyAcquisitionHelper): Response
    {
        if (!$this->isGranted(PropertyAcquisition::PERMISSION_PROPERTY_ACQUISITION_VIEW)) {
            throw new AccessDeniedHttpException(ucfirst($configurationHelper->getTranslateLocale("you_are_not_allowed_to_access_this_page")));
        }

        $filter = $propertyAcquisitionHelper->getFilter();

        return $this->render(
            'PropertyAcquisition/index.html.twig',
            [
                'filter'               => $filter,
                'propertyAcquisitions' => $propertyAcquisitionHelper->getPropertyAcquisitions($filter),
                'totalNames'           => $propertyAcquisitionHelper->getTotalNames()
            ]
        );
    }
}
