<?php

namespace App\Controller\User;

use App\Entity\GroupGta;
use App\Entity\Locale;
use App\Entity\User;
use App\Helper\ConfigurationHelper;
use App\Helper\UserHelper;
use App\Service\ErrorResponder;
use Doctrine\DBAL\Exception as DoctrineDBALException;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\ExpressionLanguage\Expression;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class FormController extends AbstractController
{
    /**
     * @throws DoctrineDBALException
     */
    #[Route(path: '/user/form', name: 'form_user', methods: ['POST'])]
    #[IsGranted(new Expression('is_granted("ROLE_ADMIN")'))]
    public function form(
        ConfigurationHelper $configurationHelper, EntityManagerInterface $entityManager, ErrorResponder $errorResponder, Request $request,
        UserHelper          $userHelper, UserPasswordHasherInterface $userPasswordHasher
    ): Response {
        $type = $request->request->get('userId') !== "" ? 'update' : 'add';

        if ($message = $configurationHelper->getMissingFieldMessage(
            $request, [
                'lastname'   => 'please_enter_the_last_name',
                'firstname'  => 'please_enter_the_first_name',
                'mail'       => 'please_enter_the_mail',
                'username'   => 'please_enter_the_username',
                'groupGtaId' => 'please_select_the_group'
            ]
        )) {
            return new JsonResponse(['message' => $message, 'success' => false]);
        }

        $password = (string)$request->request->get('password');

        if ($type === 'add' && $password === '') {
            return new JsonResponse(
                ['message' => ucfirst($configurationHelper->getTranslateLocale('please_enter_the_password')), 'success' => false]
            );
        }

        if ($password !== '' && $message = $userHelper->getPasswordMessage(
            $password, trim((string)$request->request->get('lastname')), trim((string)$request->request->get('firstname')),
            trim((string)$request->request->get('username')), trim((string)$request->request->get('mail'))
        )) {
            return new JsonResponse(['message' => $message, 'success' => false]);
        }

        if (!in_array($request->request->get('role'), $userHelper->getRoles(), true)) {
            return new JsonResponse(
                ['message' => ucfirst($configurationHelper->getTranslateLocale('please_select_the_role')), 'success' => false]
            );
        }

        try {
            if ($type === 'update') {
                $user = $entityManager->getRepository(User::class)->find($request->request->get('userId'));
            } else {
                $user = new User();
            }

            $user->setGroupGta($entityManager->getRepository(GroupGta::class)->find($request->request->get('groupGtaId')));
            $user->setLastname(trim($request->request->get('lastname')));
            $user->setFirstname(trim($request->request->get('firstname')));
            $user->setMail(trim($request->request->get('mail')));

            if ($password !== '') {
                $user->setPassword($userPasswordHasher->hashPassword($user, $password));
            }

            $user->setUsername(trim($request->request->get('username')));
            $user->setActive($request->request->get('active'));
            $user->setRoles([$request->request->get('role')]);
            $user->setPersonalDatas(true);

            if ($type === 'add') {
                $user->setLocale(
                    $entityManager->getRepository(Locale::class)->findOneBy(['code' => $configurationHelper->getDefaultLocale()])
                    ?? $entityManager->getRepository(Locale::class)->findOneBy(['default' => true])
                );
                $user->setVerifyMail(false);
                $user->setVerifyCode('');
            }

            $entityManager->persist($user);
            $entityManager->flush();

            if ($type === 'add') {
                $userHelper->sendLink($user, UserHelper::VERIFY_MAIL);
            } elseif ($password !== '') {
                $userHelper->sendPasswordChanged($user);
            }

            $filter = $userHelper->getFilter();

            return new JsonResponse(
                [
                    'filter'   => $this->renderView(
                        'User/_partial/filter.html.twig',
                        [
                            'filter'            => $filter,
                            'filterActives'     => $userHelper->getFilterActives($filter),
                            'filterGroupGtaIds' => $userHelper->getFilterGroupGtaIds($filter),
                            'filterRoles'       => $userHelper->getFilterRoles($filter)
                        ]
                    ),
                    'message'  => ucfirst(
                        $configurationHelper->getTranslateLocale(
                            'success_user_form', ['name' => trim($request->request->get('firstname')) . ' ' . trim($request->request->get('lastname')), 'type' => $type]
                        )
                    ),
                    'success'  => true,
                    'template' => $this->renderView(
                        'User/_partial/template.html.twig',
                        [
                            'filter'            => $filter,
                            'filterActives'     => $userHelper->getFilterActives($filter),
                            'filterGroupGtaIds' => $userHelper->getFilterGroupGtaIds($filter),
                            'filterRoles'       => $userHelper->getFilterRoles($filter),
                            'totalNames'        => $userHelper->getTotalNames(),
                            'users'             => $userHelper->getUsers($filter)
                        ]
                    ),
                    'users'    => $userHelper->getUsers($filter)
                ]
            );
        } catch (Exception $exception) {
            $message = ucfirst(
                $configurationHelper->getTranslateLocale(
                    'error_user_form', ['name' => trim($request->request->get('firstname')) . ' ' . trim($request->request->get('lastname')), 'type' => $type]
                )
            );

            return $errorResponder->json($exception, $message);
        }
    }
}
