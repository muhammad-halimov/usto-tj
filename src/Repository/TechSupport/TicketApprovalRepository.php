<?php

namespace App\Repository\TechSupport;

use App\Entity\TechSupport\TicketApproval;
use App\Entity\Ticket\Ticket;
use DateTimeImmutable;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<TicketApproval>
 */
class TicketApprovalRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, TicketApproval::class);
    }

    /**
     * Заявка на подтверждение того же тикета, которую можно переиспользовать
     * под новую правку вместо создания дублирующей — см. TicketListener::
     * resolveApproval(). Условия:
     *   - approved = false — уже одобренную заявку переоткрывать нельзя
     *     (TicketApproval::setApproved() и так бросает исключение на
     *     попытку сбросить true → false, но до неё лучше не доходить: у
     *     "уже закрытой" заявки другой смысл, чем у текущей неразобранной);
     *   - createdAt в пределах последних $since — старее не переиспользуем,
     *     чтобы правки не копились в одной и той же заявке бесконечно
     *     (см. вызывающий код: окно — 24 часа).
     * Если подходящих несколько (не должно бывать в норме, но на случай
     * гонки/ручного вмешательства) — берём самую свежую.
     */
    public function findReusableForTicket(Ticket $ticket, DateTimeImmutable $since): ?TicketApproval
    {
        return $this->createQueryBuilder('ta')
            ->andWhere('ta.ticket = :ticket')
            ->andWhere('ta.approved = false')
            ->andWhere('ta.createdAt >= :since')
            ->setParameter('ticket', $ticket)
            ->setParameter('since', $since)
            ->orderBy('ta.createdAt', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
