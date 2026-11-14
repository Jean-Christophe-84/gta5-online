<?php

declare(strict_types=1);

namespace App\Security\Voter;

use App\Entity\Permission;
use App\Entity\PermissionGroupGta;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\VoterInterface;

class PermissionVoter implements VoterInterface
{
    public function __construct(private readonly EntityManagerInterface $entityManager)
    {
    }

    /**
     * Ne vote que pour les attributs de la table permission : les autres (ROLE_*, IS_AUTHENTICATED_*...) sont laissés aux autres voters
     */
    public function vote(TokenInterface $token, $subject, array $attributes, ?Vote $vote = null): int
    {
        $permission = null;

        if (is_string($attributes[0] ?? null)) {
            $permission = $this->entityManager->getRepository(Permission::class)->findOneBy(['attribute' => $attributes[0]]);
        }

        if (!$permission instanceof Permission) {
            return self::ACCESS_ABSTAIN;
        }

        $user = $token->getUser();

        if (!$user instanceof User || !$this->entityManager->getRepository(PermissionGroupGta::class)->findOneBy(
            ['permission' => $permission, 'groupGta' => $user->getGroupGta(), 'active' => 1]
        )) {
            return self::ACCESS_DENIED;
        }

        return self::ACCESS_GRANTED;
    }
}
