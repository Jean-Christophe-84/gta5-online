<?php

declare(strict_types=1);

namespace App\Controller\LegalMention;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class IndexController extends AbstractController
{
    #[Route(path: '/legal-mentions', name: 'legal_mentions')]
    public function index(): Response
    {
        return $this->render('LegalMention/index.html.twig');
    }
}
