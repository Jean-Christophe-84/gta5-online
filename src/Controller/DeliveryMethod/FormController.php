<?php

namespace App\Controller\DeliveryMethod;

use App\Entity\DeliveryMethod;
use App\Entity\DeliveryMethodLocale;
use App\Entity\Locale;
use App\Entity\User;
use App\Helper\ConfigurationHelper;
use App\Helper\DeliveryMethodHelper;
use App\Service\ErrorResponder;
use Doctrine\DBAL\Exception as DoctrineDBALException;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\ExpressionLanguage\Expression;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class FormController extends AbstractController
{
    /**
     * @throws DoctrineDBALException
     */
    #[Route(path: '/delivery-method/form', name: 'form_delivery-method', methods: ['POST'])]
    #[IsGranted(new Expression('is_granted("ROLE_USER")'))]
    public function form(
        ConfigurationHelper $configurationHelper, DeliveryMethodHelper $deliveryMethodHelper, EntityManagerInterface $entityManager,
        ErrorResponder      $errorResponder, Request $request
    ): Response {
        $type = $request->request->get('deliveryMethodId') !== "" ? 'update' : 'add';

        if ($message = $configurationHelper->getMissingFieldMessage($request, ['name' => 'please_enter_the_name'])) {
            return new JsonResponse(['message' => $message, 'success' => false]);
        }

        try {
            /** @var User $user */
            $user = $this->getUser();

            if ($type === 'update') {
                if (!$this->isGranted(DeliveryMethod::PERMISSION_DELIVERY_METHOD_EDIT)) {
                    return new JsonResponse(
                        ['message' => ucfirst($configurationHelper->getTranslateLocale('you_are_not_allowed_to_access_this_page')), 'success' => false]
                    );
                }

                $deliveryMethod = $entityManager->getRepository(DeliveryMethod::class)->find($request->request->get('deliveryMethodId'));
            } else {
                if (!$this->isGranted(DeliveryMethod::PERMISSION_DELIVERY_METHOD_ADD)) {
                    return new JsonResponse(
                        ['message' => ucfirst($configurationHelper->getTranslateLocale('you_are_not_allowed_to_access_this_page')), 'success' => false]
                    );
                }

                $deliveryMethod = new DeliveryMethod();
            }

            $locale = $entityManager->getRepository(Locale::class)->find($user->getLocale()->id);

            $deliveryMethodLocale = $entityManager->getRepository(DeliveryMethodLocale::class)->findOneBy(
                ['deliveryMethod' => $deliveryMethod, 'locale' => $locale]
            );

            if (!$deliveryMethodLocale instanceof DeliveryMethodLocale) {
                $deliveryMethodLocale = new DeliveryMethodLocale();
                $deliveryMethodLocale->setDeliveryMethod($deliveryMethod);
                $deliveryMethodLocale->setLocale($locale);
            }
            $deliveryMethodLocale->setName(trim($request->request->get('name')));

            $entityManager->persist($deliveryMethodLocale);
            $entityManager->persist($deliveryMethod);
            $entityManager->flush();

            $filter          = $deliveryMethodHelper->getFilter();
            $deliveryMethods = $deliveryMethodHelper->getDeliveryMethods($filter);

            return new JsonResponse(
                [
                    'deliveryMethods' => $deliveryMethods,
                    'filter'          => $this->renderView('DeliveryMethod/_partial/filter.html.twig', ['filter' => $filter]),
                    'message'         => ucfirst(
                        $configurationHelper->getTranslateLocale(
                            'success_delivery_method_form', ['name' => trim($request->request->get('name')), 'type' => $type]
                        )
                    ),
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
            $message = ucfirst(
                $configurationHelper->getTranslateLocale(
                    'error_delivery_method_form', ['name' => trim($request->request->get('name')), 'type' => $type]
                )
            );

            return $errorResponder->json($exception, $message);
        }
    }
}
