<?php

namespace App\EventListener;

use App\Entity\TechSupport\TechSupportMessage;
use App\Service\Extra\MercurePublisher;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsEntityListener;
use Doctrine\ORM\Events;

/**
 * MERCURE для техподдержки — см. ChatMessageListener для общего объяснения
 * механизма (тот же MercurePublisher, тот же формат конверта событий).
 *
 * В отличие от чата: здесь публикуется ТОЛЬКО создание нового сообщения
 * (postPersist). Обновление и удаление сообщений техподдержки по фронтенду
 * не транслируются — постраничные GET-запросы достаточны для этих случаев.
 *
 * Топик: "tech-support:{techSupportId}" — см. TechSupport::getMercureTopic()
 * и ApiGetTechSupportSubscribeTokenController.
 */
#[AsEntityListener(event: Events::postPersist, entity: TechSupportMessage::class)]
class TechSupportMessageListener
{
    public function __construct(private readonly MercurePublisher $publisher) {}

    public function postPersist(TechSupportMessage $message): void
    {
        $techSupportId = $message->getTechSupport()?->getId();
        if (!$techSupportId) return;

        $this->publisher->publish("tech-support:{$techSupportId}", 'created', $message, ['techSupportMessages:read']);
    }
}
