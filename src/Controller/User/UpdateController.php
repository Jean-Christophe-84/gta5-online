<?php

namespace App\Controller\User;

use App\Helper\ConfigurationHelper;
use App\Helper\UserHelper;
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
     * @throws DoctrineDBALException
     */
    #[Route(path: '/user/update', name: 'update_user', methods: ['POST'])]
    #[IsGranted(new Expression('is_granted("ROLE_ADMIN")'))]
    public function update(
        ConfigurationHelper $configurationHelper, EntityManagerInterface $entityManager, ErrorResponder $errorResponder, Request $request,
        UserHelper       $userHelper
    ): Response {
        $user = $userHelper->getUser((int)$request->request->get('userId'));

        if (!$user) {
            return new JsonResponse(['message' => ucfirst($configurationHelper->getTranslateLocale('a_technical_error_occurred')), 'success' => false]);
        }

        try {
            $sql        = "UPDATE user SET user.active = :active, user.updated_at = :updatedAt, user.updated_by_id = :updatedById WHERE user.id = :userId";
            $parameters = [
                'active'      => $request->request->get('active'),
                'updatedAt'   => date('Y-m-d H:i:s'),
                'updatedById' => $this->getUser()->id,
                'userId'      => $request->request->get('userId')
            ];

            $entityManager->getConnection()->executeStatement($sql, $parameters);

            $filter = $userHelper->getFilter();

            return new JsonResponse(
                [
                    'filter'   => $this->renderView(
                        'User/_partial/filter.html.twig',
                        [
                            'filter'            => $filter,
                            'filterActives'     => $userHelper->getFilterActives($filter),
                            'filterGroupGtaIds' => $userHelper->getFilterGroupGtaIds($filter),
                            'filterRoles'       => $userHelper->getFilterRoles($filter)
                        ]
                    ),
                    'success'  => true,
                    'template' => $this->renderView(
                        'User/_partial/template.html.twig',
                        [
                            'filter'            => $filter,
                            'filterActives'     => $userHelper->getFilterActives($filter),
                            'filterGroupGtaIds' => $userHelper->getFilterGroupGtaIds($filter),
                            'filterRoles'       => $userHelper->getFilterRoles($filter),
                            'totalNames'        => $userHelper->getTotalNames(),
                            'users'             => $userHelper->getUsers($filter)
                        ]
                    ),
                    'users'    => $userHelper->getUsers($filter)
                ]
            );
        } catch (Exception $exception) {
            $message = ucfirst(
                $configurationHelper->getTranslateLocale('error_user_list', ['name' => $user['firstname'] . ' ' . $user['lastname']])
            );

            return $errorResponder->json($exception, $message);
        }
    }
}
