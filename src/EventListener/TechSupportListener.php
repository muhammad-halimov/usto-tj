<?php

namespace App\EventListener;

use App\Entity\TechSupport\TechSupport;
use App\Service\Extra\AdminLoadBalancerService;
use App\Service\Notification\Email\NotifyNewTechSupportEmailService;
use App\Service\Notification\Telegram\NotifyNewTechSupportTelegramBotService;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsEntityListener;
use Doctrine\ORM\Events;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * Обрабатывает бизнес-логику тикетов техподдержки:
 *
 *  prePersist  — назначает наименее загруженного администратора и задаёт
 *                начальный статус «new» (до записи в БД)
 *  postPersist — отправляет уведомление назначенному администратору
 *                (после успешной записи, когда у тикета есть ID)
 *
 * Балансировка нагрузки:
 *   При создании каждого нового тикета мы ищем администратора с наименьшим
 *   числом активных тикетов (статусы: new / renewed / in_progress).
 *   Это простой алгоритм round-robin по нагрузке без внешних очередей.
 *
 * Уведомления (Email/Telegram) отправляются независимо друг от друга —
 * сбой одного канала не должен блокировать другой.
 */
#[AsEntityListener(event: Events::prePersist, entity: TechSupport::class)]
#[AsEntityListener(event: Events::postPersist, entity: TechSupport::class)]
readonly class TechSupportListener
{
    public function __construct(
        private NotifyNewTechSupportTelegramBotService $telegramNotifier,
        private NotifyNewTechSupportEmailService       $emailNotifier,
        private AdminLoadBalancerService               $adminLoadBalancerService,
        private LoggerInterface                         $logger,
    ){}

    /**
     * До создания ТП задаем наименее загруженного админа
     */
    public function prePersist(TechSupport $techSupport): void
    {
        // Устанавливаем статус "new" если не задан
        if ($techSupport->getStatus() === null) $techSupport->setStatus('new');

        // Назначаем наименее загруженного администратора
        $this->adminLoadBalancerService->setLeastLoadedAdmin($techSupport, ['status' => ['new', 'renewed', 'in_progress']]);
    }

    /**
     * После создания ТП отправляем уведомление на почту и тг админа.
     * Каналы независимы: падение одного не должно мешать отправке другого.
     */
    public function postPersist(TechSupport $techSupport): void
    {
        $admin = $techSupport->getAdministrant();
        if ($admin === null || !in_array('ROLE_ADMIN', $admin->getRoles())) return;

        try {
            $this->telegramNotifier->sendTechSupportNotification(user: $admin, techSupport: $techSupport);
        } catch (Throwable $e) {
            $this->logger->error('Не удалось отправить Telegram-уведомление о TechSupport', [
                'techSupportId' => $techSupport->getId(),
                'adminId'       => $admin->getId(),
                'exception'     => $e,
            ]);
        }

        try {
            $this->emailNotifier->sendTechSupportNotification(user: $admin, techSupport: $techSupport);
        } catch (Throwable $e) {
            $this->logger->error('Не удалось отправить email-уведомление о TechSupport', [
                'techSupportId' => $techSupport->getId(),
                'adminId'       => $admin->getId(),
                'exception'     => $e,
            ]);
        }
    }
}
