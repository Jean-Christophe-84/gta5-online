<?php

namespace App\Controller\DeliveryMethod;

use App\Entity\DeliveryMethod;
use App\Helper\ConfigurationHelper;
use App\Helper\DeliveryMethodHelper;
use App\Service\ErrorResponder;
use Doctrine\DBAL\Exception as DoctrineDBALException;
use Exception;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\ExpressionLanguage\Expression;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class DeleteController extends AbstractController
{
    /**
     * @throws DoctrineDBALException
     */
    #[Route(path: '/delivery-method/delete', name: 'delete_delivery-method', methods: ['POST'])]
    #[IsGranted(new Expression('is_granted("ROLE_USER")'))]
    public function delete(
        ConfigurationHelper $configurationHelper, DeliveryMethodHelper $deliveryMethodHelper, ErrorResponder $errorResponder, Request $request
    ): Response {
        if (!$this->isGranted(DeliveryMethod::PERMISSION_DELIVERY_METHOD_DELETE)) {
            return new JsonResponse(
                ['message' => ucfirst($configurationHelper->getTranslateLocale('you_are_not_allowed_to_access_this_page')), 'success' => false]
            );
        }

        $deliveryMethodId = (int)$request->request->get('deliveryMethodId');

        if (!$deliveryMethod = $deliveryMethodHelper->getDeliveryMethod($deliveryMethodId)) {
            return new JsonResponse(['message' => ucfirst($configurationHelper->getTranslateLocale('a_technical_error_occurred')), 'success' => false]);
        }

        // @note: the modal already shows the blockers, they are checked again here as it is the only check that counts
        if (!empty($deliveryMethodHelper->getDeletion($deliveryMethodId)['blockers'])) {
            return new JsonResponse(['message' => ucfirst($configurationHelper->getTranslateLocale('deletion_impossible')), 'success' => false]);
        }

        try {
            $deliveryMethodHelper->delete($deliveryMethodId);

            $filter          = $deliveryMethodHelper->getFilter();
            $deliveryMethods = $deliveryMethodHelper->getDeliveryMethods($filter);

            return new JsonResponse(
                [
                    'deliveryMethods' => $deliveryMethods,
                    'filter'          => $this->renderView('DeliveryMethod/_partial/filter.html.twig', ['filter' => $filter]),
                    'message'         => ucfirst($configurationHelper->getTranslateLocale('success_delivery_method_delete', ['name' => $deliveryMethod['name']])),
                    'success'         => true,
                    'template'        => $this->renderView(
                        'DeliveryMethod/_partial/template.html.twig',
                        [
                            'deliveryMethods' => $deliveryMethods,
                            'filter'          => $filter,
                            'totalNames'      => $deliveryMethodHelper->getTotalNames()
                        ]
                    )
                ]
            );
        } catch (Exception $exception) {
            $message = ucfirst($configurationHelper->getTranslateLocale('error_delivery_method_delete', ['name' => $deliveryMethod['name']]));

            return $errorResponder->json($exception, $message);
        }
    }
}
