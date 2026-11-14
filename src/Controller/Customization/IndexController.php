<?php

namespace App\Controller\Customization;

use App\Entity\Customization;
use App\Helper\ConfigurationHelper;
use App\Helper\CustomizationHelper;
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
    #[Route(path: '/customizations', name: 'customizations')]
    #[IsGranted(new Expression('is_granted("ROLE_USER")'))]
    public function index(ConfigurationHelper $configurationHelper, CustomizationHelper $customizationHelper): Response
    {
        if (!$this->isGranted(Customization::PERMISSION_CUSTOMIZATION_VIEW)) {
            throw new AccessDeniedHttpException(ucfirst($configurationHelper->getTranslateLocale("you_are_not_allowed_to_access_this_page")));
        }

        $filter = $customizationHelper->getFilter();

        return $this->render(
            'Customization/index.html.twig',
            [
                'customizations' => $customizationHelper->getCustomizations($filter),
                'filter'         => $filter,
                'totalNames'     => $customizationHelper->getTotalNames()
            ]
        );
    }
}
