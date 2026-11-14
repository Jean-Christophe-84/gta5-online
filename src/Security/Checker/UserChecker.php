<?php

declare(strict_types=1);

namespace App\Security\Checker;

use App\Entity\User;
use App\Helper\ConfigurationHelper;
use Doctrine\DBAL\Exception as DoctrineDBALException;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAccountStatusException;
use Symfony\Component\Security\Core\User\UserCheckerInterface;
use Symfony\Component\Security\Core\User\UserInterface;

readonly class UserChecker implements UserCheckerInterface
{
    public function __construct(private ConfigurationHelper $configurationHelper)
    {
    }

    public function checkPreAuth(UserInterface $user): void
    {
    }

    /**
     * Après la vérification du mot de passe : avant, le message révélerait qu'un compte existe pour ce mail
     *
     * @throws DoctrineDBALException
     */
    public function checkPostAuth(UserInterface $user, ?TokenInterface $token = null): void
    {
        if (!$user instanceof User) {
            return;
        }

        if (!$user->getActive()) {
            throw new CustomUserMessageAccountStatusException(ucfirst($this->configurationHelper->getTranslateLocale('this_account_is_disabled')) . '.');
        }

        if (!$user->getVerifyMail()) {
            throw new CustomUserMessageAccountStatusException(
                ucfirst($this->configurationHelper->getTranslateLocale('the_account_exists_but_it_was_not_verified')) . '. '
                . ucfirst($this->configurationHelper->getTranslateLocale('please_click_on_the_link_received_in_the_mail_for_totally_complete_your_register')) . '.'
            );
        }
    }
}
