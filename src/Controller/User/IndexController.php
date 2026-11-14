<?php

namespace App\Controller\User;

use App\Helper\UserHelper;
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
    #[Route(path: '/users', name: 'users')]
    #[IsGranted(new Expression('is_granted("ROLE_ADMIN")'))]
    public function index(UserHelper $userHelper): Response
    {
        $filter = $userHelper->getFilter();

        return $this->render(
            'User/index.html.twig',
            [
                'filter'            => $filter,
                'filterActives'     => $userHelper->getFilterActives($filter),
                'filterGroupGtaIds' => $userHelper->getFilterGroupGtaIds($filter),
                'filterRoles'       => $userHelper->getFilterRoles($filter),
                'totalNames'        => $userHelper->getTotalNames(),
                'users'             => $userHelper->getUsers($filter)
            ]
        );
    }
}
