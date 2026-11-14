<?php

namespace App\Controller\User;

use App\Entity\GroupGtaLocale;
use App\Entity\Locale;
use App\Entity\User;
use App\Helper\UserHelper;
use Doctrine\DBAL\Exception as DoctrineDBALException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\ExpressionLanguage\Expression;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class GetController extends AbstractController
{
    /**
     * @throws DoctrineDBALException
     */
    #[Route(path: '/user/get', name: 'get_user')]
    #[IsGranted(new Expression('is_granted("ROLE_ADMIN")'))]
    public function getUserBody(EntityManagerInterface $entityManager, Request $request, UserHelper $userHelper): Response {
        if ($request->query->get('userId')) {
            $user = $userHelper->getUser($request->query->get('userId'));
        } else {
            $user = null;
        }

        /** @var User $currentUser */
        $currentUser = $this->getUser();
        $locale      = $entityManager->getRepository(Locale::class)->find($currentUser->getLocale()->id);

        return new JsonResponse(
            [
                'success'  => true,
                'template' => $this->renderView(
                    'User/_partial/body.html.twig',
                    [
                        'groupGtaLocales' => $entityManager->getRepository(GroupGtaLocale::class)->findBy(['locale' => $locale], ['name' => 'ASC']),
                        'roles'           => $userHelper->getRoles(),
                        'user'            => $user
                    ]
                )
            ]
        );
    }
}
