<?php

namespace App\Controller\Weapon;

use App\Entity\User;
use App\Entity\Weapon;
use App\Helper\ConfigurationHelper;
use App\Helper\WeaponHelper;
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

class UpdateController extends AbstractController
{
    /**
     * @throws DoctrineDBALException|Exception
     */
    #[Route(path: '/weapon/update', name: 'update_weapon', methods: ['POST'])]
    #[IsGranted(new Expression('is_granted("ROLE_USER")'))]
    public function update(
        ConfigurationHelper $configurationHelper, EntityManagerInterface $entityManager, ErrorResponder $errorResponder, Request $request,
        WeaponHelper        $weaponHelper
    ): Response {
        if (!$this->isGranted(Weapon::PERMISSION_WEAPON_OWN)) {
            return new JsonResponse(
                ['message' => ucfirst($configurationHelper->getTranslateLocale('you_are_not_allowed_to_access_this_page')), 'success' => false]
            );
        }

        /** @var User $user */
        $user   = $this->getUser();
        $weapon = $weaponHelper->getWeapon((int)$request->request->get('weaponId'));

        if (!$weapon) {
            return new JsonResponse(['message' => ucfirst($configurationHelper->getTranslateLocale('a_technical_error_occurred')), 'success' => false]);
        }

        try {
            $sql        = "
                UPDATE weapon
                LEFT JOIN weapon_user ON weapon.id = weapon_user.weapon_id AND weapon_user.user_id = :userId
                SET weapon_user.owned = :owned, weapon_user.updated_at = :updatedAt, weapon_user.updated_by_id = :userId
                WHERE weapon.id = :weaponId
            ";
            $parameters = [
                'owned'     => $request->request->get('owned'),
                'updatedAt' => date('Y-m-d H:i:s'),
                'userId'    => $user->id,
                'weaponId'  => $request->request->get('weaponId')
            ];

            $entityManager->getConnection()->executeStatement($sql, $parameters);

            if ($request->request->get('owned')) {
                $weaponHelper->releaseLinkedEvolutions((int)$request->request->get('weaponId'));
            }

            $parameters = $weaponHelper->getParameters($weaponHelper->getFilter());

            return new JsonResponse(
                [
                    'filter'    => $this->renderView('Weapon/_partial/filter.html.twig', $parameters),
                    'filterTwo' => $this->renderView('Weapon/_partial/filter_two.html.twig', $parameters),
                    'message'   => ucfirst(
                        $configurationHelper->getTranslateLocale('success_weapon_list', ['name' => $weapon['name']])
                    ),
                    'success'   => true,
                    'summary'   => $this->renderView('_partial/summary.html.twig', ['entity' => 'weapons', 'summary' => $parameters['summary']]),
                    'template'  => $this->renderView('Weapon/_partial/template.html.twig', $parameters),
                    'weapons'   => $parameters['weapons']
                ]
            );
        } catch (Exception $exception) {
            $message = ucfirst(
                $configurationHelper->getTranslateLocale('error_weapon_list', ['name' => $weapon['name']])
            );

            return $errorResponder->json($exception, $message);
        }
    }
}
