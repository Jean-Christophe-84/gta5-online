<?php

declare(strict_types=1);

namespace App\Controller\User;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\ExpressionLanguage\Expression;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class AccountController extends AbstractController
{
    #[Route(path: '/user/account', name: 'account_user')]
    #[IsGranted(new Expression('is_granted("ROLE_USER")'))]
    public function account(): Response
    {
        return $this->render('User/account.html.twig', ['user' => $this->getUser()]);
    }
}
