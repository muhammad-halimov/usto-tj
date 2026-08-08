<?php

namespace App\Repository\TechSupport;

use App\Entity\TechSupport\TechSupport;
use App\Entity\TechSupport\TechSupportMessage;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<TechSupportMessage>
 */
class TechSupportMessageRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, TechSupportMessage::class);
    }

    /**
     * Сообщения тикета, ещё не прочитанные $reader-ом: написаны не им самим
     * и readAt ещё не проставлен. Аналог ChatMessageRepository::findUnreadByRecipient().
     *
     * @return TechSupportMessage[]
     */
    public function findUnreadByRecipient(TechSupport $techSupport, User $reader): array
    {
        return $this->createQueryBuilder('m')
            ->where('m.techSupport = :techSupport')
            ->andWhere('m.author != :reader')
            ->andWhere('m.readAt IS NULL')
            ->setParameter('techSupport', $techSupport)
            ->setParameter('reader', $reader)
            ->getQuery()
            ->getResult();
    }

    //    /**
    //     * @return TechSupportMessage[] Returns an array of TechSupportMessage objects
    //     */
    //    public function findByExampleField($value): array
    //    {
    //        return $this->createQueryBuilder('t')
    //            ->andWhere('t.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->orderBy('t.id', 'ASC')
    //            ->setMaxResults(10)
    //            ->getQuery()
    //            ->getResult()
    //        ;
    //    }

    //    public function findOneBySomeField($value): ?TechSupportMessage
    //    {
    //        return $this->createQueryBuilder('t')
    //            ->andWhere('t.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->getQuery()
    //            ->getOneOrNullResult()
    //        ;
    //    }
}
