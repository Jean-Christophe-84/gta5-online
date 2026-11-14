<?php

namespace App\Controller\TitleUpdate;

use App\Entity\TitleUpdate;
use App\Helper\ConfigurationHelper;
use App\Helper\TitleUpdateHelper;
use Doctrine\DBAL\Exception as DoctrineDBALException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\ExpressionLanguage\Expression;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class FilterController extends AbstractController
{
    /**
     * @throws DoctrineDBALException
     */
    #[Route(path: '/title-update/filter', name: 'filter_title-update')]
    #[IsGranted(new Expression('is_granted("ROLE_USER")'))]
    public function filter(
        ConfigurationHelper $configurationHelper, Request $request, RequestStack $requestStack, TitleUpdateHelper $titleUpdateHelper
    ): Response {
        if (!$this->isGranted(TitleUpdate::PERMISSION_TITLE_UPDATE_VIEW)) {
            return new JsonResponse(
                ['message' => ucfirst($configurationHelper->getTranslateLocale('you_are_not_allowed_to_access_this_page')), 'success' => false]
            );
        }

        $filter['search'] = (string)$request->query->get('search');
        $filter['page']   = max(1, (int)$request->query->get('page'));

        if (!$request->query->get('orderNew')) {
            $filter['order'] = $titleUpdateHelper->getFilter()['order'];
        }

        foreach ($request->query->all('orders') as $key => $order) {
            $filter['order'][$key] = strtoupper($order);
        }

        $requestStack->getSession()->set('titleUpdate', $filter);

        $filter = $titleUpdateHelper->getFilter();

        return new JsonResponse(
            [
                'filter'       => $this->renderView('TitleUpdate/_partial/filter.html.twig', ['filter' => $filter]),
                'success'      => true,
                'template'     => $this->renderView(
                    'TitleUpdate/_partial/template.html.twig',
                    [
                        'filter'       => $filter,
                        'titleUpdates' => $titleUpdateHelper->getTitleUpdates($filter),
                        'totalNames'   => $titleUpdateHelper->getTotalNames()
                    ]
                ),
                'titleUpdates' => $titleUpdateHelper->getTitleUpdates($filter)
            ]
        );
    }
}
