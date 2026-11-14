<?php

namespace App\Controller\GroupGta;

use App\Helper\ConfigurationHelper;
use App\Helper\GroupGtaHelper;
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
     * @throws DoctrineDBALException|Exception
     */
    #[Route(path: '/group-gta/filter', name: 'filter_group-gta')]
    #[IsGranted(new Expression('is_granted("ROLE_ADMIN")'))]
    public function filter(
        ConfigurationHelper $configurationHelper, ErrorResponder $errorResponder, GroupGtaHelper $groupGtaHelper, Request $request,
        RequestStack        $requestStack
    ): JsonResponse {
        try {
            $filter['search'] = (string)$request->query->get('search');
            $filter['page']   = max(1, (int)$request->query->get('page'));

            if (!$request->query->get('orderNew')) {
                $filter['order'] = $groupGtaHelper->getFilter()['order'];
            }

            foreach ($request->query->all('orders') as $key => $order) {
                $filter['order'][$key] = strtoupper($order);
            }

            $requestStack->getSession()->set('groupGta', $filter);

            $filter    = $groupGtaHelper->getFilter();
            $groupGtas = $groupGtaHelper->getGroupGtas($filter);

            return new JsonResponse(
                [
                    'filter'    => $this->renderView('GroupGta/_partial/filter.html.twig', ['filter' => $filter]),
                    'groupGtas' => $groupGtas,
                    'success'   => true,
                    'template'  => $this->renderView(
                        'GroupGta/_partial/template.html.twig',
                        [
                            'filter'     => $filter,
                            'groupGtas'  => $groupGtas,
                            'totalNames' => $groupGtaHelper->getTotalNames()
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
