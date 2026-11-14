<?php

declare(strict_types=1);

namespace App\Controller\LoginError;

use App\Helper\LoginErrorHelper;
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
    #[Route(path: '/login-errors', name: 'login-errors')]
    #[IsGranted(new Expression('is_granted("ROLE_ADMIN")'))]
    public function index(LoginErrorHelper $loginErrorHelper): Response
    {
        $filter = $loginErrorHelper->getFilter();

        return $this->render(
            'LoginError/index.html.twig',
            [
                'filter'      => $filter,
                'loginErrors' => $loginErrorHelper->getLoginErrors($filter),
                'totalNames'  => $loginErrorHelper->getTotalNames()
            ]
        );
    }
}
