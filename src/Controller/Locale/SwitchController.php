<?php

namespace App\Controller\Locale;

use App\Entity\Locale;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

class SwitchController extends AbstractController
{
    /**
     * @throws Exception
     */
    #[Route(path: '/locale/switch', name: 'switch_locale', methods: ['POST'])]
    public function switch(EntityManagerInterface $entityManager, Request $request): RedirectResponse
    {
        $targetPath = (string)$request->request->get('_target_path');
        $redirect   = preg_match('#^/(?![/\\\\])#', $targetPath) ? $this->redirect($targetPath) : $this->redirectToRoute('homepage');

        if (!$this->isCsrfTokenValid('switch_locale', $request->request->get('_token'))) {
            return $redirect;
        }

        $locale = $entityManager->getRepository(Locale::class)->findOneBy(['code' => $request->request->get('code')]);

        if (!$locale instanceof Locale) {
            return $redirect;
        }

        $user = $this->getUser();

        if ($user instanceof User && $user->getLocale()->id !== $locale->id) {
            $user->setLocale($locale);

            $entityManager->persist($user);
            $entityManager->flush();
        }

        $request->getSession()->set('default_locale', $locale->getCode());

        return $redirect;
    }
}
