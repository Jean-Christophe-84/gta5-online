<?php

namespace App\Controller\User;

use App\Entity\User;
use App\Helper\ConfigurationHelper;
use App\Helper\UserHelper;
use App\Service\AfterResponse;
use App\Service\ErrorResponder;
use Doctrine\DBAL\Exception as DoctrineDBALException;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use Symfony\Component\Routing\Attribute\Route;

class ForgotPasswordController extends AbstractController
{
    /**
     * Même réponse que le mail corresponde à un compte ou non, pour ne pas révéler les comptes existants
     *
     * @throws DoctrineDBALException
     */
    #[Route(path: '/user/forgot-password', name: 'forgot_password_user', methods: ['POST'])]
    public function forgotPassword(
        AfterResponse       $afterResponse, ConfigurationHelper $configurationHelper, EntityManagerInterface $entityManager, ErrorResponder $errorResponder, Request $request,
        RateLimiterFactoryInterface $accountMailLimiter, UserHelper $userHelper
    ): JsonResponse {
        if ($message = $configurationHelper->getMissingFieldMessage($request, ['mail' => 'please_enter_the_mail'])) {
            return new JsonResponse(['message' => $message, 'success' => false]);
        }

        if (!$accountMailLimiter->create($request->getClientIp())->consume()->isAccepted()) {
            return new JsonResponse(['message' => ucfirst($configurationHelper->getTranslateLocale('too_many_requests_please_try_again_later')), 'success' => false]);
        }

        try {
            $mail = trim((string)$request->request->get('mail'));

            $afterResponse->add(function () use ($entityManager, $mail, $userHelper) {
                $user = $entityManager->getRepository(User::class)->findOneBy(['mail' => $mail]);

                if ($user && $user->getActive()) {
                    $userHelper->sendLink($user, UserHelper::RESET_PASSWORD);
                }
            });

            return new JsonResponse(
                ['message' => ucfirst($configurationHelper->getTranslateLocale('if_an_account_exists_with_this_mail_a_link_has_been_sent_to_it')), 'success' => true]
            );
        } catch (Exception $exception) {
            return $errorResponder->json($exception, ucfirst($configurationHelper->getTranslateLocale('an_error_occurred_while_sending_the_mail')));
        }
    }
}
