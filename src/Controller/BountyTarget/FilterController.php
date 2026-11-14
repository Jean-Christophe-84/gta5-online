<?php

namespace App\Controller\BountyTarget;

use App\Entity\BountyTarget;
use App\Helper\BountyTargetHelper;
use App\Helper\ConfigurationHelper;
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
    #[Route(path: '/bounty-target/filter', name: 'filter_bounty-target')]
    #[IsGranted(new Expression('is_granted("ROLE_USER")'))]
    public function filter(
        BountyTargetHelper $bountyTargetHelper, ConfigurationHelper $configurationHelper, Request $request, RequestStack $requestStack
    ): Response {
        if (!$this->isGranted(BountyTarget::PERMISSION_BOUNTY_TARGET_VIEW)) {
            return new JsonResponse(
                ['message' => ucfirst($configurationHelper->getTranslateLocale('you_are_not_allowed_to_access_this_page')), 'success' => false]
            );
        }

        $filter['search'] = (string)$request->query->get('search');
        $filter['page']   = max(1, (int)$request->query->get('page'));

        if (!$request->query->get('orderNew')) {
            $filter['order'] = $bountyTargetHelper->getFilter()['order'];
        }

        foreach ($request->query->all('orders') as $key => $order) {
            $filter['order'][$key] = strtoupper($order);
        }

        $requestStack->getSession()->set('bountyTarget', $filter);

        $parameters = $bountyTargetHelper->getParameters($bountyTargetHelper->getFilter());

        return new JsonResponse(
            [
                'bountyTargets' => $parameters['bountyTargets'],
                'filter'        => $this->renderView('BountyTarget/_partial/filter.html.twig', $parameters),
                'success'       => true,
                'summary'       => $this->renderView('_partial/summary.html.twig', ['entity' => 'bounty_targets', 'summary' => $parameters['summary']]),
                'template'      => $this->renderView('BountyTarget/_partial/template.html.twig', $parameters)
            ]
        );
    }
}
