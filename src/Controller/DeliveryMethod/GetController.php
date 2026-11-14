<?php

namespace App\Controller\DeliveryMethod;

use App\Entity\DeliveryMethod;
use App\Helper\ConfigurationHelper;
use App\Helper\DeliveryMethodHelper;
use App\Service\DeletionResponder;
use Exception;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\ExpressionLanguage\Expression;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class GetController extends AbstractController
{
    /**
     * @throws Exception
     */
    #[Route(path: '/delivery-method/get', name: 'get_delivery-method')]
    #[IsGranted(new Expression('is_granted("ROLE_USER")'))]
    public function getDeliveryMethod(
        ConfigurationHelper $configurationHelper, DeletionResponder $deletionResponder, DeliveryMethodHelper $deliveryMethodHelper, Request $request
    ): Response {
        $delete = $request->query->getBoolean('delete');

        if ($request->query->get('deliveryMethodId')) {
            if (!$this->isGranted($delete ? DeliveryMethod::PERMISSION_DELIVERY_METHOD_DELETE : DeliveryMethod::PERMISSION_DELIVERY_METHOD_EDIT)) {
                return new JsonResponse(
                    ['message' => ucfirst($configurationHelper->getTranslateLocale('you_are_not_allowed_to_access_this_page')), 'success' => false]
                );
            }

            $deliveryMethod = $deliveryMethodHelper->getDeliveryMethod($request->query->get('deliveryMethodId'));
        } else {
            if ($delete || !$this->isGranted(DeliveryMethod::PERMISSION_DELIVERY_METHOD_ADD)) {
                return new JsonResponse(
                    ['message' => ucfirst($configurationHelper->getTranslateLocale('you_are_not_allowed_to_access_this_page')), 'success' => false]
                );
            }

            $deliveryMethod = null;
        }

        $template = $this->renderView('DeliveryMethod/_partial/body.html.twig', ['deliveryMethod' => $deliveryMethod]);

        if ($delete) {
            return $deletionResponder->json($template, $deliveryMethodHelper->getDeletion((int)$request->query->get('deliveryMethodId')));
        }

        return new JsonResponse(['success' => true, 'template' => $template]);
    }
}
