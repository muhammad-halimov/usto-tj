<?php

namespace App\EventListener;

use App\Entity\TechSupport\TechSupportMessage;
use App\Entity\User;
use App\Service\Extra\MercurePublisher;
use App\Service\Notification\Email\NotifyNewTechSupportEmailService;
use App\Service\Notification\Telegram\NotifyNewTechSupportTelegramBotService;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsEntityListener;
use Doctrine\ORM\Events;
use Psr\Log\LoggerInterface;
use Throwable;

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
 *
 * Плюс Email/Telegram-уведомление назначенному администранту — те же каналы,
 * что TechSupportListener уже использует при создании тикета (см. там),
 * просто другой метод (sendTechSupportMessageNotification). Не шлём, если
 * сообщение написал сам администрант — незачем уведомлять о своём же ответе.
 */
#[AsEntityListener(event: Events::postPersist, entity: TechSupportMessage::class)]
class TechSupportMessageListener
{
    public function __construct(
        private readonly MercurePublisher                      $publisher,
        private readonly NotifyNewTechSupportTelegramBotService $telegramNotifier,
        private readonly NotifyNewTechSupportEmailService       $emailNotifier,
        private readonly LoggerInterface                        $logger,
    ) {}

    public function postPersist(TechSupportMessage $message): void
    {
        $techSupport = $message->getTechSupport();

        if (!$techSupport?->getId()) return;

        $this->publisher->publish("tech-support:{$techSupport->getId()}", 'created', $message, ['techSupportMessages:read']);

        $this->notifyAdmin($message, $techSupport->getAdministrant());
    }

    /**
     * Email/Telegram каналы независимы: падение одного не должно мешать
     * отправке другого (тот же принцип, что в TechSupportListener::postPersist).
     */
    private function notifyAdmin(TechSupportMessage $message, ?User $admin): void
    {
        if ($admin === null || !in_array('ROLE_ADMIN', $admin->getRoles(), true)) return;
        if ($admin === $message->getAuthor()) return; // не уведомляем админа о его же сообщении

        try {
            $this->telegramNotifier->sendTechSupportMessageNotification(user: $admin, message: $message);
        } catch (Throwable $e) {
            $this->logger->error('Не удалось отправить Telegram-уведомление о новом сообщении TechSupport', [
                'techSupportId'        => $message->getTechSupport()?->getId(),
                'techSupportMessageId' => $message->getId(),
                'adminId'              => $admin->getId(),
                'exception'            => $e,
            ]);
        }

        try {
            $this->emailNotifier->sendTechSupportMessageNotification(user: $admin, message: $message);
        } catch (Throwable $e) {
            $this->logger->error('Не удалось отправить email-уведомление о новом сообщении TechSupport', [
                'techSupportId'        => $message->getTechSupport()?->getId(),
                'techSupportMessageId' => $message->getId(),
                'adminId'              => $admin->getId(),
                'exception'            => $e,
            ]);
        }
    }
}
