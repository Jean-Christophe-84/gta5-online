<?php

namespace App\Controller\WeaponAcquisition;

use App\Entity\WeaponAcquisition;
use App\Helper\ConfigurationHelper;
use App\Helper\WeaponAcquisitionHelper;
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
    #[Route(path: '/weapon-acquisition/delete', name: 'delete_weapon-acquisition', methods: ['POST'])]
    #[IsGranted(new Expression('is_granted("ROLE_USER")'))]
    public function delete(
        ConfigurationHelper $configurationHelper, ErrorResponder $errorResponder, Request $request, WeaponAcquisitionHelper $weaponAcquisitionHelper
    ): Response {
        if (!$this->isGranted(WeaponAcquisition::PERMISSION_WEAPON_ACQUISITION_DELETE)) {
            return new JsonResponse(
                ['message' => ucfirst($configurationHelper->getTranslateLocale('you_are_not_allowed_to_access_this_page')), 'success' => false]
            );
        }

        $weaponAcquisitionId = (int)$request->request->get('weaponAcquisitionId');

        if (!$weaponAcquisition = $weaponAcquisitionHelper->getWeaponAcquisition($weaponAcquisitionId)) {
            return new JsonResponse(['message' => ucfirst($configurationHelper->getTranslateLocale('a_technical_error_occurred')), 'success' => false]);
        }

        // @note: the modal already shows the blockers, they are checked again here as it is the only check that counts
        if (!empty($weaponAcquisitionHelper->getDeletion($weaponAcquisitionId)['blockers'])) {
            return new JsonResponse(['message' => ucfirst($configurationHelper->getTranslateLocale('deletion_impossible')), 'success' => false]);
        }

        try {
            $weaponAcquisitionHelper->delete($weaponAcquisitionId);

            $filter = $weaponAcquisitionHelper->getFilter();

            return new JsonResponse(
                [
                    'filter'             => $this->renderView('WeaponAcquisition/_partial/filter.html.twig', ['filter' => $filter]),
                    'message'            => ucfirst($configurationHelper->getTranslateLocale('success_weapon_acquisition_delete', ['name' => $weaponAcquisition['name']])),
                    'success'            => true,
                    'template'           => $this->renderView(
                        'WeaponAcquisition/_partial/template.html.twig',
                        [
                            'filter'             => $filter,
                            'totalNames'         => $weaponAcquisitionHelper->getTotalNames(),
                            'weaponAcquisitions' => $weaponAcquisitionHelper->getWeaponAcquisitions($filter)
                        ]
                    ),
                    'weaponAcquisitions' => $weaponAcquisitionHelper->getWeaponAcquisitions($filter)
                ]
            );
        } catch (Exception $exception) {
            $message = ucfirst($configurationHelper->getTranslateLocale('error_weapon_acquisition_delete', ['name' => $weaponAcquisition['name']]));

            return $errorResponder->json($exception, $message);
        }
    }
}
