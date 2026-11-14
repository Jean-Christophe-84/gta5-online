<?php

namespace App\Controller\User;

use App\Helper\ConfigurationHelper;
use App\Helper\UserStatisticHelper;
use App\Service\ErrorResponder;
use DateTime;
use Doctrine\DBAL\Exception as DoctrineDBALException;
use Exception;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\ExpressionLanguage\Expression;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class StatisticController extends AbstractController
{
    /**
     * @throws DoctrineDBALException
     */
    #[Route(path: '/user/statistics', name: 'statistics_user')]
    #[IsGranted(new Expression('is_granted("ROLE_ADMIN")'))]
    public function statistics(
        ConfigurationHelper $configurationHelper, ErrorResponder $errorResponder, Request $request, RequestStack $requestStack,
        UserStatisticHelper $userStatisticHelper
    ): JsonResponse {
        try {
            if ($request->query->has('start') && $request->query->has('end')) {
                $dates = array_map(
                    fn (string $date) => DateTime::createFromFormat('!Y-m-d', $date) ?: new DateTime('today'),
                    [(string)$request->query->get('start'), (string)$request->query->get('end')]
                );

                sort($dates);

                $requestStack->getSession()->set('userStatistic', ['start' => $dates[0]->format('Y-m-d'), 'end' => $dates[1]->format('Y-m-d')]);
            }

            $filter = $userStatisticHelper->getFilter();

            return new JsonResponse(
                [
                    ...$userStatisticHelper->getStatistics($filter),
                    'end'     => $filter['end'],
                    'filter'  => $filter['start'] != date('Y-m-d') || $filter['end'] != date('Y-m-d'),
                    'start'   => $filter['start'],
                    'success' => true
                ]
            );
        } catch (Exception $exception) {
            $message = ucfirst($configurationHelper->getTranslateLocale("an_error_occurred_while_displaying_the_graphic"));

            return $errorResponder->json($exception, $message);
        }
    }
}
