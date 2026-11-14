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
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use Symfony\Component\Routing\Attribute\Route;

class VerifyMailController extends AbstractController
{
    /**
     * Page du lien reçu par mail : la vérification se fait au clic sur le bouton (un antivirus qui ouvre le lien ne consomme pas le code)
     */
    #[Route(path: '/user/verify-mail/{userId}/{code}', name: 'verify_mail_user', requirements: ['userId' => '\d+', 'code' => '[a-f0-9]{64}'], methods: ['GET'])]
    public function index(int $userId, string $code): Response
    {
        return $this->render('User/verify_mail.html.twig', ['code' => $code, 'userId' => $userId]);
    }

    /**
     * @throws DoctrineDBALException
     */
    #[Route(path: '/user/verify-mail', name: 'check_verify_mail_user', methods: ['POST'])]
    public function verify(
        ConfigurationHelper $configurationHelper, EntityManagerInterface $entityManager, ErrorResponder $errorResponder, Request $request, Security $security,
        UserHelper          $userHelper
    ): JsonResponse {
        try {
            $user = $entityManager->getRepository(User::class)->find((int)$request->request->get('userId'));

            if (!$user || !$user->getActive() || $user->getVerifyMail() || !$userHelper->checkCode($user, (string)$request->request->get('code'))) {
                return new JsonResponse(
                    ['message' => ucfirst($configurationHelper->getTranslateLocale('this_link_is_invalid_or_has_expired')), 'resend' => true, 'success' => false]
                );
            }

            $userHelper->invalidateCode($user);
            $user->setVerifyMail(true);

            $entityManager->flush();

            $security->login($user, 'form_login', 'main');

            return new JsonResponse(['redirect' => $this->generateUrl('homepage'), 'success' => true]);
        } catch (Exception $exception) {
            return $errorResponder->json($exception, ucfirst($configurationHelper->getTranslateLocale('an_error_occurred_while_verifying_the_mail')));
        }
    }

    /**
     * Renvoie un lien de vérification : même réponse que le compte existe ou non
     *
     * @throws DoctrineDBALException
     */
    #[Route(path: '/user/verify-mail/resend', name: 'resend_verify_mail_user', methods: ['POST'])]
    public function resend(
        AfterResponse       $afterResponse, ConfigurationHelper $configurationHelper, EntityManagerInterface $entityManager, ErrorResponder $errorResponder, Request $request,
        RateLimiterFactoryInterface $accountMailLimiter, UserHelper $userHelper
    ): JsonResponse {
        if (!$accountMailLimiter->create($request->getClientIp())->consume()->isAccepted()) {
            return new JsonResponse(['message' => ucfirst($configurationHelper->getTranslateLocale('too_many_requests_please_try_again_later')), 'success' => false]);
        }

        try {
            $userId = (int)$request->request->get('userId');

            $afterResponse->add(function () use ($entityManager, $userHelper, $userId) {
                $user = $entityManager->getRepository(User::class)->find($userId);

                if ($user && $user->getActive() && !$user->getVerifyMail()) {
                    $userHelper->sendLink($user, UserHelper::VERIFY_MAIL);
                }
            });

            return new JsonResponse(['message' => ucfirst($configurationHelper->getTranslateLocale('a_new_link_has_been_sent_to_you_by_mail')), 'success' => true]);
        } catch (Exception $exception) {
            return $errorResponder->json($exception, ucfirst($configurationHelper->getTranslateLocale('an_error_occurred_while_sending_the_mail')));
        }
    }
}
