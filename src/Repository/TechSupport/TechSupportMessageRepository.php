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

    /**
     * "Оператор ответил на [это] сообщение" — приблизительно, раз у
     * TechSupportMessage нет своего replyTo (в отличие от ChatMessage):
     * считаем, что администрант отреагировал, если после $message успел
     * написать в тот же тикет хотя бы одно своё сообщение. Используется
     * ApiPatchTechSupportMessageController — "правка до реакции оператора"
     * (см. checkOwnership()).
     */
    public function existsAdministrantMessageAfter(TechSupportMessage $message): bool
    {
        $techSupport  = $message->getTechSupport();
        $administrant = $techSupport?->getAdministrant();

        if (!$administrant) return false;

        return $this->createQueryBuilder('m')
            ->select('1')
            ->where('m.techSupport = :techSupport')
            ->andWhere('m.author = :administrant')
            ->andWhere('m.createdAt > :after')
            ->setParameter('techSupport', $techSupport)
            ->setParameter('administrant', $administrant)
            ->setParameter('after', $message->getCreatedAt())
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult() !== null;
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
