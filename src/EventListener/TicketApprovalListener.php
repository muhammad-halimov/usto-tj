<?php

namespace App\EventListener;

use App\Entity\TechSupport\TicketApproval;
use App\Service\Extra\AdminLoadBalancerService;
use App\Service\Notification\Email\NotifyNewTicketApprovalEmailService;
use App\Service\Notification\Telegram\NotifyNewTicketApprovalTelegramBotService;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsEntityListener;
use Doctrine\ORM\Events;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;

/**
 * Обрабатывает бизнес-логику тикетов техподдержки:
 *
 *  prePersist  — назначает наименее загруженного администратора и задаёт
 *                начальный статус «unchecked» (до записи в БД)
 *  postPersist — отправляет уведомление назначенному администратору
 *                (после успешной записи, когда у тикета есть ID)
 *
 * Балансировка нагрузки:
 *   При создании каждого нового тикета мы ищем администратора с наименьшим
 *   числом активных тикетов (статусы: unchecked).
 *   Это простой алгоритм round-robin по нагрузке без внешних очередей.
 */
#[AsEntityListener(event: Events::prePersist, entity: TicketApproval::class)]
#[AsEntityListener(event: Events::postPersist, entity: TicketApproval::class)]
readonly class TicketApprovalListener
{
    public function __construct(
        private AdminLoadBalancerService                  $adminLoadBalancerService,
        private NotifyNewTicketApprovalEmailService       $emailNotifier,
        private NotifyNewTicketApprovalTelegramBotService $telegramNotifier,
    ){}

    /**
     * До создания объявления/услуги задаем наименее загруженного админа
     */
    public function prePersist(TicketApproval $ticketApproval): void
    {
        // Назначаем наименее загруженного администратора
        $this->adminLoadBalancerService->setLeastLoadedAdmin($ticketApproval, ['approved' => false]);
    }

    /**
     * После создания объявления/услуги отправляем уведомление на почту и тг админа
     * @throws TransportExceptionInterface
     */
    public function postPersist(TicketApproval $ticketApproval): void
    {
        $admin = $ticketApproval->getAdministrant();
        if ($admin === null || !in_array('ROLE_ADMIN', $admin->getRoles())) return;

        $this->emailNotifier->sendTicketApprovalNotification($admin, $ticketApproval);
        $this->telegramNotifier->sendTicketApprovalNotification($admin, $ticketApproval);
    }
}
