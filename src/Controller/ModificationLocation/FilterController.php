<?php

namespace App\Controller\ModificationLocation;

use App\Entity\ModificationLocation;
use App\Helper\ConfigurationHelper;
use App\Helper\ModificationLocationHelper;
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
    #[Route(path: '/modification-location/filter', name: 'filter_modification-location')]
    #[IsGranted(new Expression('is_granted("ROLE_USER")'))]
    public function filter(
        ConfigurationHelper $configurationHelper, ModificationLocationHelper $modificationLocationHelper, Request $request,
        RequestStack $requestStack
    ): Response {
        if (!$this->isGranted(ModificationLocation::PERMISSION_MODIFICATION_LOCATION_VIEW)) {
            return new JsonResponse(
                ['message' => ucfirst($configurationHelper->getTranslateLocale('you_are_not_allowed_to_access_this_page')), 'success' => false]
            );
        }

        $filter['search'] = (string)$request->query->get('search');
        $filter['page']   = max(1, (int)$request->query->get('page'));

        if (!$request->query->get('orderNew')) {
            $filter['order'] = $modificationLocationHelper->getFilter()['order'];
        }

        foreach ($request->query->all('orders') as $key => $order) {
            $filter['order'][$key] = strtoupper($order);
        }

        $requestStack->getSession()->set('modificationLocation', $filter);

        $filter                = $modificationLocationHelper->getFilter();
        $modificationLocations = $modificationLocationHelper->getModificationLocations($filter);

        return new JsonResponse(
            [
                'filter'                => $this->renderView('ModificationLocation/_partial/filter.html.twig', ['filter' => $filter]),
                'modificationLocations' => $modificationLocations,
                'success'               => true,
                'template'              => $this->renderView(
                    'ModificationLocation/_partial/template.html.twig',
                    [
                        'filter'                => $filter,
                        'modificationLocations' => $modificationLocations,
                        'totalNames'            => $modificationLocationHelper->getTotalNames()
                    ]
                )
            ]
        );
    }
}
