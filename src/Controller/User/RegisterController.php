<?php

namespace App\Controller\User;

use App\Entity\GroupGta;
use App\Entity\Locale;
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
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use Symfony\Component\Routing\Attribute\Route;

class RegisterController extends AbstractController
{
    /**
     * @throws DoctrineDBALException
     */
    #[Route(path: '/user/register', name: 'register_user', methods: ['POST'])]
    public function register(
        AfterResponse       $afterResponse, ConfigurationHelper $configurationHelper, EntityManagerInterface $entityManager, ErrorResponder $errorResponder, Request $request,
        RateLimiterFactoryInterface $accountMailLimiter, UserHelper $userHelper, UserPasswordHasherInterface $userPasswordHasher
    ): JsonResponse {
        if ($message = $configurationHelper->getMissingFieldMessage(
            $request, [
                'lastname'  => 'please_enter_the_last_name',
                'firstname' => 'please_enter_the_first_name',
                'mail'      => 'please_enter_the_mail',
                'password'  => 'please_enter_the_password',
                'username'  => 'please_enter_the_username'
            ]
        )) {
            return new JsonResponse(['message' => $message, 'success' => false]);
        }

        if (!$request->request->getBoolean('personalDatas')) {
            return new JsonResponse(
                ['message' => ucfirst($configurationHelper->getTranslateLocale('please_accept_the_privacy_policy_to_create_your_account')), 'success' => false]
            );
        }

        $mail = trim((string)$request->request->get('mail'));

        foreach (['lastname', 'firstname', 'mail', 'username'] as $field) {
            if (mb_strlen(trim((string)$request->request->get($field))) > UserHelper::MAX_FIELD_LENGTH) {
                return new JsonResponse(
                    [
                        'message' => ucfirst($configurationHelper->getTranslateLocale('the_fields_cannot_exceed_characters', ['count' => UserHelper::MAX_FIELD_LENGTH])),
                        'success' => false
                    ]
                );
            }
        }

        if (!filter_var($mail, FILTER_VALIDATE_EMAIL)) {
            return new JsonResponse(['message' => ucfirst($configurationHelper->getTranslateLocale('please_enter_a_valid_mail')), 'success' => false]);
        }

        if ($message = $userHelper->getPasswordMessage(
            (string)$request->request->get('password'), trim((string)$request->request->get('lastname')), trim((string)$request->request->get('firstname')),
            trim((string)$request->request->get('username')), $mail
        )) {
            return new JsonResponse(['message' => $message, 'success' => false]);
        }

        if (!$accountMailLimiter->create($request->getClientIp())->consume()->isAccepted()) {
            return new JsonResponse(['message' => ucfirst($configurationHelper->getTranslateLocale('too_many_requests_please_try_again_later')), 'success' => false]);
        }

        try {
            $user = new User();
            $user->setGroupGta($entityManager->getRepository(GroupGta::class)->findOneBy(['default' => true]));
            $user->setLocale(
                $entityManager->getRepository(Locale::class)->findOneBy(['code' => $configurationHelper->getDefaultLocale()])
                ?? $entityManager->getRepository(Locale::class)->findOneBy(['default' => true])
            );
            $user->setLastname(trim($request->request->get('lastname')));
            $user->setFirstname(trim($request->request->get('firstname')));
            $user->setMail($mail);
            $user->setPassword($userPasswordHasher->hashPassword($user, $request->request->get('password')));
            $user->setUsername(trim($request->request->get('username')));
            $user->setActive(true);
            $user->setRoles(['ROLE_USER']);
            $user->setVerifyMail(false);
            $user->setVerifyCode('');
            $user->setPersonalDatas(true);

            $afterResponse->add(function () use ($entityManager, $mail, $user, $userHelper) {
                if (($existingUser = $entityManager->getRepository(User::class)->findOneBy(['mail' => $mail])) instanceof User) {
                    $userHelper->sendAccountAlreadyExists($existingUser);

                    return;
                }

                $entityManager->persist($user);
                $entityManager->flush();

                $userHelper->sendLink($user, UserHelper::VERIFY_MAIL);
            });

            return new JsonResponse(
                ['message' => ucfirst($configurationHelper->getTranslateLocale('a_mail_has_been_sent_to_you_to_finalize_your_registration')), 'success' => true]
            );
        } catch (Exception $exception) {
            return $errorResponder->json($exception, ucfirst($configurationHelper->getTranslateLocale('an_error_occurred_while_creating_the_account')));
        }
    }
}
