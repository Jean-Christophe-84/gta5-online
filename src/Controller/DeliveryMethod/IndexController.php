<?php

namespace App\Controller\DeliveryMethod;

use App\Entity\DeliveryMethod;
use App\Helper\ConfigurationHelper;
use App\Helper\DeliveryMethodHelper;
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
    #[Route(path: '/delivery-methods', name: 'delivery-methods')]
    #[IsGranted(new Expression('is_granted("ROLE_USER")'))]
    public function index(ConfigurationHelper $configurationHelper, DeliveryMethodHelper $deliveryMethodHelper): Response
    {
        if (!$this->isGranted(DeliveryMethod::PERMISSION_DELIVERY_METHOD_VIEW)) {
            throw new AccessDeniedHttpException(ucfirst($configurationHelper->getTranslateLocale("you_are_not_allowed_to_access_this_page")));
        }

        $filter = $deliveryMethodHelper->getFilter();

        return $this->render(
            'DeliveryMethod/index.html.twig',
            [
                'deliveryMethods' => $deliveryMethodHelper->getDeliveryMethods($filter),
                'filter'          => $filter,
                'totalNames'      => $deliveryMethodHelper->getTotalNames()
            ]
        );
    }
}
