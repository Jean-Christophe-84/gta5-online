<?php

namespace App\Controller\Weapon;

use App\Entity\Weapon;
use App\Helper\ConfigurationHelper;
use App\Helper\WeaponHelper;
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
    #[Route(path: '/weapon/delete', name: 'delete_weapon', methods: ['POST'])]
    #[IsGranted(new Expression('is_granted("ROLE_USER")'))]
    public function delete(
        ConfigurationHelper $configurationHelper, ErrorResponder $errorResponder, Request $request, WeaponHelper $weaponHelper
    ): Response {
        if (!$this->isGranted(Weapon::PERMISSION_WEAPON_DELETE)) {
            return new JsonResponse(
                ['message' => ucfirst($configurationHelper->getTranslateLocale('you_are_not_allowed_to_access_this_page')), 'success' => false]
            );
        }

        $weaponId = (int)$request->request->get('weaponId');

        if (!$weapon = $weaponHelper->getWeapon($weaponId)) {
            return new JsonResponse(['message' => ucfirst($configurationHelper->getTranslateLocale('a_technical_error_occurred')), 'success' => false]);
        }

        if (!empty($weaponHelper->getDeletion($weaponId)['blockers'])) {
            return new JsonResponse(['message' => ucfirst($configurationHelper->getTranslateLocale('deletion_impossible')), 'success' => false]);
        }

        try {
            $weaponHelper->delete($weaponId);

            $parameters = $weaponHelper->getParameters($weaponHelper->getFilter());

            return new JsonResponse(
                [
                    'filter'    => $this->renderView('Weapon/_partial/filter.html.twig', $parameters),
                    'filterTwo' => $this->renderView('Weapon/_partial/filter_two.html.twig', $parameters),
                    'message'   => ucfirst($configurationHelper->getTranslateLocale('success_weapon_delete', ['name' => $weapon['name']])),
                    'success'   => true,
                    'summary'   => $this->renderView('_partial/summary.html.twig', ['entity' => 'weapons', 'summary' => $parameters['summary']]),
                    'template'  => $this->renderView('Weapon/_partial/template.html.twig', $parameters),
                    'weapons'   => $parameters['weapons']
                ]
            );
        } catch (Exception $exception) {
            $message = ucfirst($configurationHelper->getTranslateLocale('error_weapon_delete', ['name' => $weapon['name']]));

            return $errorResponder->json($exception, $message);
        }
    }
}
