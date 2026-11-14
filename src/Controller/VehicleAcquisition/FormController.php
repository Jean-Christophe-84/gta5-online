<?php

namespace App\Controller\VehicleAcquisition;

use App\Entity\Locale;
use App\Entity\User;
use App\Entity\VehicleAcquisition;
use App\Entity\VehicleAcquisitionLocale;
use App\Helper\ConfigurationHelper;
use App\Helper\VehicleAcquisitionHelper;
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
    #[Route(path: '/vehicle-acquisition/form', name: 'form_vehicle-acquisition', methods: ['POST'])]
    #[IsGranted(new Expression('is_granted("ROLE_USER")'))]
    public function form(
        ConfigurationHelper      $configurationHelper, EntityManagerInterface $entityManager, ErrorResponder $errorResponder, Request $request,
        VehicleAcquisitionHelper $vehicleAcquisitionHelper
    ): Response {
        $type = $request->request->get('vehicleAcquisitionId') !== "" ? 'update' : 'add';

        if ($message = $configurationHelper->getMissingFieldMessage($request, ['name' => 'please_enter_the_name'])) {
            return new JsonResponse(['message' => $message, 'success' => false]);
        }

        try {
            /** @var User $user */
            $user = $this->getUser();

            if ($type === 'update') {
                if (!$this->isGranted(VehicleAcquisition::PERMISSION_VEHICLE_ACQUISITION_EDIT)) {
                    return new JsonResponse(
                        ['message' => ucfirst($configurationHelper->getTranslateLocale('you_are_not_allowed_to_access_this_page')), 'success' => false]
                    );
                }

                $vehicleAcquisition = $entityManager->getRepository(VehicleAcquisition::class)->find($request->request->get('vehicleAcquisitionId'));
            } else {
                if (!$this->isGranted(VehicleAcquisition::PERMISSION_VEHICLE_ACQUISITION_ADD)) {
                    return new JsonResponse(
                        ['message' => ucfirst($configurationHelper->getTranslateLocale('you_are_not_allowed_to_access_this_page')), 'success' => false]
                    );
                }

                $vehicleAcquisition = new VehicleAcquisition();
            }

            $locale = $entityManager->getRepository(Locale::class)->find($user->getLocale()->id);

            $vehicleAcquisitionLocale = $entityManager->getRepository(VehicleAcquisitionLocale::class)->findOneBy(
                ['vehicleAcquisition' => $vehicleAcquisition, 'locale' => $locale]
            );

            if (!$vehicleAcquisitionLocale instanceof VehicleAcquisitionLocale) {
                $vehicleAcquisitionLocale = new VehicleAcquisitionLocale();
                $vehicleAcquisitionLocale->setVehicleAcquisition($vehicleAcquisition);
                $vehicleAcquisitionLocale->setLocale($locale);
            }

            $vehicleAcquisitionLocale->setName(trim($request->request->get('name')));

            $vehicleAcquisition->setAcquirable((bool)$request->request->get('acquirable'));
            $vehicleAcquisition->setAvailable((bool)$request->request->get('available'));
            $vehicleAcquisition->setPromotion((bool)$request->request->get('promotion'));

            $entityManager->persist($vehicleAcquisitionLocale);
            $entityManager->persist($vehicleAcquisition);
            $entityManager->flush();

            $filter = $vehicleAcquisitionHelper->getFilter();

            return new JsonResponse(
                [
                    'filter'              => $this->renderView('VehicleAcquisition/_partial/filter.html.twig', ['filter' => $filter]),
                    'message'             => ucfirst(
                        $configurationHelper->getTranslateLocale(
                            'success_vehicle_acquisition_form', ['name' => trim($request->request->get('name')), 'type' => $type]
                        )
                    ),
                    'success'             => true,
                    'template'            => $this->renderView(
                        'VehicleAcquisition/_partial/template.html.twig',
                        [
                            'filter'              => $filter,
                            'totalNames'          => $vehicleAcquisitionHelper->getTotalNames(),
                            'vehicleAcquisitions' => $vehicleAcquisitionHelper->getVehicleAcquisitions($filter)
                        ]
                    ),
                    'vehicleAcquisitions' => $vehicleAcquisitionHelper->getVehicleAcquisitions($filter)
                ]
            );
        } catch (Exception $exception) {
            $message = ucfirst(
                $configurationHelper->getTranslateLocale(
                    'error_vehicle_acquisition_form', ['name' => trim($request->request->get('name')), 'type' => $type]
                )
            );

            return $errorResponder->json($exception, $message);
        }
    }
}
