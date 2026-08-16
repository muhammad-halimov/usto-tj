<?php

namespace App\Repository\Extra;

use App\Entity\Extra\EntityRevision;
use DateTimeImmutable;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<EntityRevision>
 */
class EntityRevisionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, EntityRevision::class);
    }

    /**
     * Записи, у которых retention включён (expiresAt не null) и срок истёк.
     * Используется app:prune-entity-revisions.
     */
    public function findExpired(): array
    {
        return $this->createQueryBuilder('r')
            ->where('r.expiresAt IS NOT NULL')
            ->andWhere('r.expiresAt < :now')
            ->setParameter('now', new DateTimeImmutable())
            ->getQuery()
            ->getResult();
    }
}
