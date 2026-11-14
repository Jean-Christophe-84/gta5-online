<?php

namespace App\Controller\VehicleManufacturer;

use App\Entity\VehicleManufacturer;
use App\Helper\ConfigurationHelper;
use App\Helper\VehicleManufacturerHelper;
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
    #[Route(path: '/vehicle-manufacturer/form', name: 'form_vehicle-manufacturer', methods: ['POST'])]
    #[IsGranted(new Expression('is_granted("ROLE_USER")'))]
    public function form(
        ConfigurationHelper       $configurationHelper, EntityManagerInterface $entityManager, ErrorResponder $errorResponder, Request $request,
        VehicleManufacturerHelper $vehicleManufacturerHelper
    ): Response {
        $type = $request->request->get('vehicleManufacturerId') !== "" ? 'update' : 'add';

        if ($message = $configurationHelper->getMissingFieldMessage($request, ['name' => 'please_enter_the_name'])) {
            return new JsonResponse(['message' => $message, 'success' => false]);
        }

        try {
            if ($type === 'update') {
                if (!$this->isGranted(VehicleManufacturer::PERMISSION_VEHICLE_MANUFACTURER_EDIT)) {
                    return new JsonResponse(
                        ['message' => ucfirst($configurationHelper->getTranslateLocale('you_are_not_allowed_to_access_this_page')), 'success' => false]
                    );
                }

                $vehicleManufacturer =
                    $entityManager->getRepository(VehicleManufacturer::class)->find($request->request->get('vehicleManufacturerId'));
            } else {
                if (!$this->isGranted(VehicleManufacturer::PERMISSION_VEHICLE_MANUFACTURER_ADD)) {
                    return new JsonResponse(
                        ['message' => ucfirst($configurationHelper->getTranslateLocale('you_are_not_allowed_to_access_this_page')), 'success' => false]
                    );
                }

                $vehicleManufacturer = new VehicleManufacturer();
            }
            $vehicleManufacturer->setName(trim($request->request->get('name')));
            $vehicleManufacturer->setRealName(trim($request->request->get('realName')));

            $entityManager->persist($vehicleManufacturer);
            $entityManager->flush();

            $filter = $vehicleManufacturerHelper->getFilter();

            return new JsonResponse(
                [
                    'filter'               => $this->renderView('VehicleManufacturer/_partial/filter.html.twig', ['filter' => $filter]),
                    'message'              => ucfirst(
                        $configurationHelper->getTranslateLocale(
                            'success_vehicle_manufacturer_form', ['name' => trim($request->request->get('name')), 'type' => $type]
                        )
                    ),
                    'success'              => true,
                    'template'             => $this->renderView(
                        'VehicleManufacturer/_partial/template.html.twig',
                        [
                            'filter'               => $filter,
                            'totalNames'           => $vehicleManufacturerHelper->getTotalNames(),
                            'vehicleManufacturers' => $vehicleManufacturerHelper->getVehicleManufacturers($filter)
                        ]
                    ),
                    'vehicleManufacturers' => $vehicleManufacturerHelper->getVehicleManufacturers($filter)
                ]
            );
        } catch (Exception $exception) {
            $message = ucfirst(
                $configurationHelper->getTranslateLocale(
                    'error_vehicle_manufacturer_form', ['name' => trim($request->request->get('name')), 'type' => $type]
                )
            );

            return $errorResponder->json($exception, $message);
        }
    }
}
