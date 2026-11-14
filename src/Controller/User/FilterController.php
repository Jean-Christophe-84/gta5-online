<?php

namespace App\Controller\User;

use App\Helper\ConfigurationHelper;
use App\Helper\UserHelper;
use App\Service\ErrorResponder;
use Doctrine\DBAL\Exception as DoctrineDBALException;
use Exception;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\ExpressionLanguage\Expression;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class FilterController extends AbstractController
{
    /**
     * @throws DoctrineDBALException
     */
    #[Route(path: '/user/filter', name: 'filter_user')]
    #[IsGranted(new Expression('is_granted("ROLE_ADMIN")'))]
    public function filter(
        ConfigurationHelper $configurationHelper, ErrorResponder $errorResponder, Request $request, RequestStack $requestStack,
        UserHelper          $userHelper
    ): JsonResponse {
        try {
            $filter['groupGtaId'] = (int)$request->query->get('groupGtaId');
            $filter['active']     = $request->query->get('active') !== "null" ? (bool)$request->query->get('active') : null;
            $filter['role']       = (string)$request->query->get('role');
            $filter['search']     = (string)$request->query->get('search');
            $filter['page']       = max(1, (int)$request->query->get('page'));

            if (!$request->query->get('orderNew')) {
                $filter['order'] = $userHelper->getFilter()['order'];
            }

            foreach ($request->query->all('orders') as $key => $order) {
                $filter['order'][$key] = strtoupper($order);
            }

            $requestStack->getSession()->set('user', $filter);

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
            $message = ucfirst($configurationHelper->getTranslateLocale("an_error_occurred_while_changing_the_filter"));

            return $errorResponder->json($exception, $message);
        }
    }
}
