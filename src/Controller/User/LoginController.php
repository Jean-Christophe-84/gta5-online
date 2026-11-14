<?php

declare(strict_types=1);

namespace App\Controller\User;

use App\Helper\UserHelper;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;

class LoginController extends AbstractController
{
    #[Route(path: 'login', name: 'login')]
    public function login(AuthenticationUtils $authenticationUtils): Response
    {
        if ($this->isGranted('IS_AUTHENTICATED_REMEMBERED')) return $this->redirectToRoute('homepage');

        return $this->render(
            'User/login.html.twig',
            [
                'checkSession'      => false,
                'error'             => $authenticationUtils->getLastAuthenticationError(),
                'last_username'     => $authenticationUtils->getLastUsername(),
                'passwordMinLength' => UserHelper::PASSWORD_MIN_LENGTH
            ]
        );
    }
}
