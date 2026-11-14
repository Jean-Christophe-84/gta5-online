<?php

namespace App\EventListener;

use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\ORM\Event\PrePersistEventArgs;
use Doctrine\ORM\Event\PreUpdateEventArgs;
use Doctrine\ORM\Events;
use Symfony\Bundle\SecurityBundle\Security;

/**
 * Remplit created_at/created_by et updated_at/updated_by des entités qui utilisent InfoUpdateTrait (écritures via l'ORM uniquement)
 */
#[AsDoctrineListener(event: Events::prePersist)]
#[AsDoctrineListener(event: Events::preUpdate)]
readonly class InfoUpdateListener
{
    public function __construct(private Security $security)
    {
    }

    public function prePersist(PrePersistEventArgs $args): void
    {
        $entity = $args->getObject();

        if (method_exists($entity, 'setCreatedBy') && !$entity instanceof User && (!$entity->getCreatedBy() && $this->security->getUser() != null)) {
            $entity->setCreatedBy($this->security->getUser());
        }

        if (method_exists($entity, 'setCreatedAt') && !$entity->getCreatedAt()) {
            $entity->setCreatedAt();
        }

        if (method_exists($entity, 'setUpdatedBy') && $this->security->getUser() != null) {
            $entity->setUpdatedBy($this->security->getUser());
        }

        if (method_exists($entity, 'setUpdatedAt')) {
            $entity->setUpdatedAt();
        }
    }

    public function preUpdate(PreUpdateEventArgs $args): void
    {
        $entity = $args->getObject();

        if (method_exists($entity, 'setUpdatedBy') && $this->security->getUser() != null) {
            $entity->setUpdatedBy($this->security->getUser());
        }

        if (method_exists($entity, 'setUpdatedAt')) {
            $entity->setUpdatedAt();
        }
    }
}
