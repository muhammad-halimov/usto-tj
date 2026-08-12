<?php

namespace App\Repository\User;

use App\Entity\User;
use App\Entity\User\BlackList;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<BlackList>
 */
class BlackListRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, BlackList::class);
    }

    /**
     * Проверяет, заблокировал ли $owner пользователя $targetUser.
     * Используется и для защиты от дублей при создании записи, и
     * в AccessService::checkBlackList() для проверки самого факта блокировки.
     */
    public function findDuplicate(User $owner, User $targetUser): ?BlackList
    {
        return $this->createQueryBuilder('b')
            ->where('b.owner = :owner')
            ->andWhere('b.user = :targetUser')
            ->setParameter('owner', $owner)
            ->setParameter('targetUser', $targetUser)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
