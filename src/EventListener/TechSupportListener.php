<?php

namespace App\EventListener;

use App\Entity\TechSupport\TechSupport;
use App\Service\Extra\AdminLoadBalancerService;
use App\Service\Notification\Email\NotifyNewTechSupportEmailService;
use App\Service\Notification\NotificationDispatcher;
use App\Service\Notification\Telegram\NotifyNewTechSupportTelegramBotService;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsEntityListener;
use Doctrine\ORM\Events;

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
 * Уведомления: сначала Telegram, email — только если Telegram не прошёл
 * (см. NotificationDispatcher — общая логика для всех notify-листенеров).
 */
#[AsEntityListener(event: Events::prePersist, entity: TechSupport::class)]
#[AsEntityListener(event: Events::postPersist, entity: TechSupport::class)]
readonly class TechSupportListener
{
    public function __construct(
        private NotifyNewTechSupportTelegramBotService $telegramNotifier,
        private NotifyNewTechSupportEmailService       $emailNotifier,
        private AdminLoadBalancerService               $adminLoadBalancerService,
        private NotificationDispatcher                 $dispatcher,
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
     * После создания ТП отправляем уведомление админу.
     */
    public function postPersist(TechSupport $techSupport): void
    {
        $admin = $techSupport->getAdministrant();
        if ($admin === null || !in_array('ROLE_ADMIN', $admin->getRoles())) return;

        $this->dispatcher->send(
            sendTelegram: fn() => $this->telegramNotifier->sendTechSupportNotification(user: $admin, techSupport: $techSupport),
            sendEmail:    fn() => $this->emailNotifier->sendTechSupportNotification(user: $admin, techSupport: $techSupport),
            label:        'TechSupport',
            logContext:   ['techSupportId' => $techSupport->getId(), 'adminId' => $admin->getId()],
        );
    }
}
