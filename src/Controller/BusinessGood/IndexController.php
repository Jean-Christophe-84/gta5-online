<?php

namespace App\Controller\BusinessGood;

use App\Entity\BusinessGood;
use App\Helper\BusinessGoodHelper;
use App\Helper\ConfigurationHelper;
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
    #[Route(path: '/business-goods', name: 'business_goods')]
    #[IsGranted(new Expression('is_granted("ROLE_USER")'))]
    public function index(BusinessGoodHelper $businessGoodHelper, ConfigurationHelper $configurationHelper): Response
    {
        if (!$this->isGranted(BusinessGood::PERMISSION_BUSINESS_GOOD_VIEW)) {
            throw new AccessDeniedHttpException(ucfirst($configurationHelper->getTranslateLocale("you_are_not_allowed_to_access_this_page")));
        }

        $filter = $businessGoodHelper->getFilter();

        return $this->render(
            'BusinessGood/index.html.twig',
            [
                'businessGoods' => $businessGoodHelper->getBusinessGoods($filter),
                'filter'        => $filter,
                'totalNames'    => $businessGoodHelper->getTotalNames()
            ]
        );
    }
}
