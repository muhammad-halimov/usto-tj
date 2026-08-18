<?php

namespace App\EventListener;

use App\Entity\Extra\EntityRevision;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsEntityListener;
use Doctrine\ORM\Event\PostUpdateEventArgs;
use Doctrine\ORM\Event\PreUpdateEventArgs;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Events;
use Symfony\Bundle\SecurityBundle\Security;

/**
 * Аудит изменений User — тот же preUpdate/postUpdate приём, что у
 * TicketListener/ReviewListener/ChatMessageListener/TechSupportMessageListener
 * (changeset доступен только в preUpdate, персист новой сущности — только в
 * postUpdate, см. подробное объяснение в TicketListener).
 *
 * Отдельный класс от UserListener (тот — только хэширование пароля, узкая
 * ответственность по докблоку) — Doctrine поддерживает несколько listener'ов
 * на одно и то же событие/сущность одновременно.
 *
 * Пока отслеживается только cookiesAgreed — намеренно узкий список, а не весь
 * User: большинство полей либо уже покрыто другими механизмами (approved/
 * banned — модерация, не audit trail), либо чувствительные (password и
 * т.п.), которым не место в снимках EntityRevision. Расширять WATCHED_FIELDS
 * по мере необходимости.
 */
#[AsEntityListener(event: Events::preUpdate, entity: User::class)]
#[AsEntityListener(event: Events::postUpdate, entity: User::class)]
class UserRevisionListener
{
    private const array WATCHED_FIELDS = ['cookiesAgreed'];

    /** @var array<int, array<string, array{old: mixed, new: mixed}>> */
    private array $pendingRevision = [];

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly Security               $security,
    ) {}

    public function preUpdate(User $user, PreUpdateEventArgs $event): void
    {
        $snapshot = [];
        foreach (self::WATCHED_FIELDS as $field) {
            if ($event->hasChangedField($field)) {
                $snapshot[$field] = [
                    'old' => $event->getOldValue($field),
                    'new' => $event->getNewValue($field),
                ];
            }
        }

        if ($snapshot) {
            $this->pendingRevision[spl_object_id($user)] = $snapshot;
        }
    }

    public function postUpdate(User $user, PostUpdateEventArgs $event): void
    {
        $key = spl_object_id($user);
        if (!isset($this->pendingRevision[$key])) return;

        $snapshot = $this->pendingRevision[$key];
        unset($this->pendingRevision[$key]);

        $revision = (new EntityRevision())
            ->setEntityType('user')
            ->setEntityId($user->getId())
            // parentId не проставляем — User корень иерархии, родителя нет
            // (та же логика, что у Ticket, см. TicketListener). entity
            // указывает сам на себя по той же причине.
            ->setEntity('User')
            ->setAction(EntityRevision::ACTION_UPDATED)
            ->setSnapshot($snapshot)
            ->setActor($this->currentUser());

        $this->entityManager->persist($revision);
        $this->entityManager->flush();
    }

    private function currentUser(): ?User
    {
        /** @var User|null $user */
        $user = $this->security->getUser();

        return $user;
    }
}
