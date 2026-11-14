<?php

namespace App\Controller\GroupGta;

use App\Helper\GroupGtaHelper;
use Doctrine\DBAL\Exception as DoctrineDBALException;
use Exception;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\ExpressionLanguage\Expression;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class IndexController extends AbstractController
{
    /**
     * @throws DoctrineDBALException|Exception
     */
    #[Route(path: '/group-gtas', name: 'group-gtas')]
    #[IsGranted(new Expression('is_granted("ROLE_ADMIN")'))]
    public function index(GroupGtaHelper $groupGtaHelper): Response
    {
        $filter = $groupGtaHelper->getFilter();

        return $this->render(
            'GroupGta/index.html.twig',
            [
                'filter'     => $filter,
                'groupGtas'  => $groupGtaHelper->getGroupGtas($filter),
                'totalNames' => $groupGtaHelper->getTotalNames(),
            ]
        );
    }
}
