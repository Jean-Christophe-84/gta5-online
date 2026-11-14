<?php

declare(strict_types=1);

namespace App\Controller\Heist\Factory;

use Exception;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\ExpressionLanguage\Expression;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class IndexController extends AbstractController
{
    /**
     * @throws Exception
     */
    #[Route(path: '/heist/factory', name: 'factory_heist')]
    #[IsGranted(new Expression('is_granted("ROLE_USER")'))]
    public function index(): Response
    {
        return $this->render('Heist/Factory/index.html.twig');
    }
}
