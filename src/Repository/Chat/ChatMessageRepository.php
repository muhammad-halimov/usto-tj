<?php

namespace App\Repository\Chat;

use App\Entity\Chat\Chat;
use App\Entity\Chat\ChatMessage;
use App\Entity\User;
use DateTimeImmutable;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ChatMessage>
 */
class ChatMessageRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ChatMessage::class);
    }

    /**
     * Все непрочитанные сообщения чата, отправленные не текущим пользователем.
     *
     * Используется в ApiPostMarkChatReadController для массовой пометки как прочитанных.
     * Загружает сущности через Unit of Work — чтобы postUpdate-события Doctrine
     * сработали и Mercure доставил SSE-уведомления отправителям.
     *
     * @return ChatMessage[]
     */
    public function findUnreadByRecipient(Chat $chat, User $reader): array
    {
        return $this->createQueryBuilder('m')
            ->where('m.chat = :chat')
            ->andWhere('m.author != :reader')
            ->andWhere('m.readAt IS NULL')
            ->setParameter('chat', $chat)
            ->setParameter('reader', $reader)
            ->getQuery()
            ->getResult();
    }

    /**
     * Сообщения чата, новые сначала — под пагинацию в
     * ApiGetChatMessagesController (см. AbstractApiGetCollectionController,
     * пагинация там накладывается снаружи через Doctrine Paginator).
     */
    public function findByChat(Chat $chat): QueryBuilder
    {
        return $this->createQueryBuilder('m')
            ->where('m.chat = :chat')
            ->setParameter('chat', $chat)
            ->orderBy('m.createdAt', 'DESC');
    }
}
