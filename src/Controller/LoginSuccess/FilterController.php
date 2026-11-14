<?php

namespace App\Controller\LoginSuccess;

use App\Helper\ConfigurationHelper;
use App\Helper\LoginSuccessHelper;
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
    #[Route(path: '/login-success/filter', name: 'filter_login-success')]
    #[IsGranted(new Expression('is_granted("ROLE_ADMIN")'))]
    public function filter(
        ConfigurationHelper $configurationHelper, ErrorResponder $errorResponder, LoginSuccessHelper $loginSuccessHelper, Request $request, RequestStack $requestStack
    ): JsonResponse {
        try {
            $filter['search'] = (string)$request->query->get('search');
            $filter['page']   = max(1, (int)$request->query->get('page'));

            if (!$request->query->get('orderNew')) {
                $filter['order'] = $loginSuccessHelper->getFilter()['order'];
            }

            foreach ($request->query->all('orders') as $key => $order) {
                $filter['order'][$key] = strtoupper($order);
            }

            $requestStack->getSession()->set('loginSuccess', $filter);

            $filter = $loginSuccessHelper->getFilter();

            return new JsonResponse(
                [
                    'filter'   => $this->renderView('LoginSuccess/_partial/filter.html.twig', ['filter' => $filter]),
                    'success'  => true,
                    'template' => $this->renderView(
                        'LoginSuccess/_partial/template.html.twig',
                        [
                            'filter'         => $filter,
                            'loginSuccesses' => $loginSuccessHelper->getLoginSuccesses($filter),
                            'totalNames'     => $loginSuccessHelper->getTotalNames()
                        ]
                    )
                ]
            );
        } catch (Exception $exception) {
            $message = ucfirst($configurationHelper->getTranslateLocale("an_error_occurred_while_changing_the_filter"));

            return $errorResponder->json($exception, $message);
        }
    }
}
