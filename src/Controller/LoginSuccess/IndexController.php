<?php

declare(strict_types=1);

namespace App\Controller\LoginSuccess;

use App\Helper\LoginSuccessHelper;
use Doctrine\DBAL\Exception as DoctrineDBALException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\ExpressionLanguage\Expression;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class IndexController extends AbstractController
{
    /**
     * @throws DoctrineDBALException
     */
    #[Route(path: '/login-successes', name: 'login-successes')]
    #[IsGranted(new Expression('is_granted("ROLE_ADMIN")'))]
    public function index(LoginSuccessHelper $loginSuccessHelper): Response
    {
        $filter = $loginSuccessHelper->getFilter();

        return $this->render(
            'LoginSuccess/index.html.twig',
            [
                'filter'         => $filter,
                'loginSuccesses' => $loginSuccessHelper->getLoginSuccesses($filter),
                'totalNames'     => $loginSuccessHelper->getTotalNames()
            ]
        );
    }
}
