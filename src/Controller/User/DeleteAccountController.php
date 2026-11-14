<?php

namespace App\Controller\User;

use App\Helper\ConfigurationHelper;
use App\Helper\UserHelper;
use App\Service\ErrorResponder;
use Doctrine\DBAL\Exception as DoctrineDBALException;
use Exception;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\ExpressionLanguage\Expression;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class DeleteAccountController extends AbstractController
{
    /**
     * @throws DoctrineDBALException
     */
    #[Route(path: '/user/account/delete', name: 'delete_account_user', methods: ['POST'])]
    #[IsGranted(new Expression('is_granted("ROLE_USER")'))]
    public function delete(
        ConfigurationHelper $configurationHelper, ErrorResponder $errorResponder, Request $request,
        TokenStorageInterface $tokenStorage, UserHelper $userHelper, UserPasswordHasherInterface $userPasswordHasher
    ): JsonResponse {
        $user = $this->getUser();

        if (!$userPasswordHasher->isPasswordValid($user, (string)$request->request->get('password'))) {
            return new JsonResponse(['message' => ucfirst($configurationHelper->getTranslateLocale('the_password_is_incorrect')), 'success' => false]);
        }

        try {
            if ($userHelper->isLastAdmin($user)) {
                return new JsonResponse(
                    ['message' => ucfirst($configurationHelper->getTranslateLocale('the_last_administrator_cannot_delete_his_account')), 'success' => false]
                );
            }

            $userHelper->anonymize($user);

            $tokenStorage->setToken(null);
            $request->getSession()->invalidate();

            return new JsonResponse(
                [
                    'message'  => ucfirst($configurationHelper->getTranslateLocale('your_account_and_your_personal_datas_have_been_deleted')),
                    'redirect' => $this->generateUrl('login'),
                    'success'  => true
                ]
            );
        } catch (Exception $exception) {
            return $errorResponder->json($exception, ucfirst($configurationHelper->getTranslateLocale('an_error_occurred_while_deleting_the_account')));
        }
    }
}
