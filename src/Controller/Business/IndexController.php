<?php

namespace App\Controller\Business;

use App\Entity\Business;
use App\Helper\BusinessHelper;
use App\Helper\ConfigurationHelper;
use Doctrine\DBAL\Exception as DoctrineDBALException;
use Exception;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\ExpressionLanguage\Expression;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class IndexController extends AbstractController
{
    /**
     * @throws DoctrineDBALException|Exception
     */
    #[Route(path: '/businesses', name: 'businesses')]
    #[IsGranted(new Expression('is_granted("ROLE_USER")'))]
    public function index(BusinessHelper $businessHelper, ConfigurationHelper $configurationHelper): Response
    {
        if (!$this->isGranted(Business::PERMISSION_BUSINESS_VIEW)) {
            throw new AccessDeniedHttpException(ucfirst($configurationHelper->getTranslateLocale("you_are_not_allowed_to_access_this_page")));
        }

        $filter = $businessHelper->getFilter();

        return $this->render(
            'Business/index.html.twig',
            [
                'businesses'            => $businessHelper->getBusinesses($filter),
                'filter'                => $filter,
                'filterPropertyTypeIds' => $businessHelper->getFilterPropertyTypeIds($filter),
                'filterTitleUpdateIds'  => $businessHelper->getFilterTitleUpdateIds($filter),
                'totalNames'            => $businessHelper->getTotalNames()
            ]
        );
    }
}
