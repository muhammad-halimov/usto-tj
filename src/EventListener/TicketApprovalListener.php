<?php

namespace App\EventListener;

use App\Entity\TechSupport\TicketApproval;
use App\Service\Extra\AdminLoadBalancerService;
use App\Service\Notification\Email\NotifyNewTicketApprovalEmailService;
use App\Service\Notification\NotificationDispatcher;
use App\Service\Notification\Telegram\NotifyNewTicketApprovalTelegramBotService;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsEntityListener;
use Doctrine\ORM\Events;

/**
 * Обрабатывает бизнес-логику тикетов техподдержки:
 *
 *  prePersist  — назначает наименее загруженного администратора
 *                (до записи в БД)
 *  postPersist — отправляет уведомление назначенному администратору
 *                (после успешной записи, когда у тикета есть ID)
 *
 * Балансировка нагрузки:
 *   При создании каждого нового тикета мы ищем администратора с наименьшим
 *   числом неодобренных заявок (approved = false).
 *   Это простой алгоритм round-robin по нагрузке без внешних очередей.
 *
 * Уведомления: сначала Telegram, email — только если Telegram не прошёл
 * (см. NotificationDispatcher — общая логика для всех notify-листенеров).
 */
#[AsEntityListener(event: Events::prePersist, entity: TicketApproval::class)]
#[AsEntityListener(event: Events::postPersist, entity: TicketApproval::class)]
readonly class TicketApprovalListener
{
    public function __construct(
        private AdminLoadBalancerService                  $adminLoadBalancerService,
        private NotifyNewTicketApprovalEmailService       $emailNotifier,
        private NotifyNewTicketApprovalTelegramBotService $telegramNotifier,
        private NotificationDispatcher                     $dispatcher,
    ){}

    /**
     * До создания объявления/услуги задаем наименее загруженного админа
     */
    public function prePersist(TicketApproval $ticketApproval): void
    {
        $this->adminLoadBalancerService->setLeastLoadedAdmin($ticketApproval, ['approved' => false]);
    }

    /**
     * После создания объявления/услуги отправляем уведомление админу.
     */
    public function postPersist(TicketApproval $ticketApproval): void
    {
        $admin = $ticketApproval->getAdministrant();
        if ($admin === null || !in_array('ROLE_ADMIN', $admin->getRoles())) return;

        $this->dispatcher->send(
            sendTelegram: fn() => $this->telegramNotifier->sendTicketApprovalNotification($admin, $ticketApproval),
            sendEmail:    fn() => $this->emailNotifier->sendTicketApprovalNotification($admin, $ticketApproval),
            label:        'TicketApproval',
            logContext:   ['ticketApprovalId' => $ticketApproval->getId(), 'adminId' => $admin->getId()],
        );
    }
}
