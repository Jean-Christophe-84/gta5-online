<?php

namespace App\Controller\GroupGta;

use App\Entity\GroupGtaLocale;
use App\Entity\Locale;
use App\Entity\User;
use App\Helper\GroupGtaHelper;
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
    #[Route(path: '/group-gta/get', name: 'get_group-gta')]
    #[IsGranted(new Expression('is_granted("ROLE_ADMIN")'))]
    public function getGroupGta(EntityManagerInterface $entityManager, GroupGtaHelper $groupGtaHelper, Request $request): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        if ($request->query->get('groupGtaId')) {
            $groupGta = $groupGtaHelper->getGroupGta($request->query->get('groupGtaId'));
        } else {
            $groupGta = null;
        }

        $locale = $entityManager->getRepository(Locale::class)->find($user->getLocale()->id);

        return new JsonResponse(
            [
                'success'  => true,
                'template' => $this->renderView(
                    'GroupGta/_partial/body.html.twig',
                    [
                        'groupGta'        => $groupGta,
                        'groupGtaLocales' => $entityManager->getRepository(GroupGtaLocale::class)->findBy(['locale' => $locale], ['name' => 'ASC'])
                    ]
                )
            ]
        );
    }
}
