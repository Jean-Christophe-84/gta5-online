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
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;

class ResetPasswordController extends AbstractController
{
    #[Route(path: '/user/reset-password/{userId}/{code}', name: 'reset_password_user', requirements: ['userId' => '\d+', 'code' => '[a-f0-9]{64}'], methods: ['GET'])]
    public function index(int $userId, string $code): Response
    {
        return $this->render(
            'User/reset_password.html.twig',
            ['code' => $code, 'passwordMinLength' => UserHelper::PASSWORD_MIN_LENGTH, 'userId' => $userId]
        );
    }

    /**
     * Le lien prouve l'accès à la boîte mail : le mail est aussi validé.
     * Les données personnelles ne sont comparées au mot de passe qu'une fois le code vérifié, sinon le message révélerait le nom d'un compte
     *
     * @throws DoctrineDBALException
     */
    #[Route(path: '/user/reset-password', name: 'check_reset_password_user', methods: ['POST'])]
    public function resetPassword(
        AfterResponse $afterResponse, ConfigurationHelper $configurationHelper, EntityManagerInterface $entityManager, ErrorResponder $errorResponder, Request $request,
        Security      $security, UserHelper $userHelper, UserPasswordHasherInterface $userPasswordHasher
    ): JsonResponse {
        $password = (string)$request->request->get('password');

        if ($message = $configurationHelper->getMissingFieldMessage($request, ['password' => 'please_enter_the_password'])) {
            return new JsonResponse(['message' => $message, 'success' => false]);
        }

        if ($password !== (string)$request->request->get('passwordConfirmation')) {
            return new JsonResponse(['message' => ucfirst($configurationHelper->getTranslateLocale('the_passwords_are_not_identical')), 'success' => false]);
        }

        try {
            $user = $entityManager->getRepository(User::class)->find((int)$request->request->get('userId'));

            if (!$user || !$user->getActive() || !$userHelper->checkCode($user, (string)$request->request->get('code'))) {
                return new JsonResponse(['message' => ucfirst($configurationHelper->getTranslateLocale('this_link_is_invalid_or_has_expired')), 'success' => false]);
            }

            if ($message = $userHelper->getPasswordMessage($password, $user->getLastname(), $user->getFirstname(), $user->getUsername(), $user->getMail())) {
                return new JsonResponse(['message' => $message, 'success' => false]);
            }

            $userHelper->invalidateCode($user);
            $user->setPassword($userPasswordHasher->hashPassword($user, $password));
            $user->setVerifyMail(true);

            $entityManager->flush();

            $afterResponse->add(fn() => $userHelper->sendPasswordChanged($user));

            $security->login($user, 'form_login', 'main');

            return new JsonResponse(['redirect' => $this->generateUrl('homepage'), 'success' => true]);
        } catch (Exception $exception) {
            return $errorResponder->json($exception, ucfirst($configurationHelper->getTranslateLocale('an_error_occurred_while_changing_the_password')));
        }
    }
}
