<?php

namespace App\Service\Notification\Abstract;

use App\Entity\TechSupport\TicketApproval;
use App\Entity\User;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Базовый класс для уведомлений о новых тикетах.
 *
 * Инкапсулирует общее:
 *   - Генерацию URL заверения тикета в админке
 *   - Отправка уведомления
 *
 * Конкретные каналы (email, Telegram) реализуют sendTicketApprovalNotification().
 */
abstract class AbstractTicketApprovalNotificationService extends AbstractMailerService
{
    public function __construct(protected readonly UrlGeneratorInterface $urlGenerator) {}

    abstract public function sendTicketApprovalNotification(User $user, TicketApproval $ticketApproval): mixed;

    protected function ticketApprovalAdminUrl(TicketApproval $ticketApproval): string
    {
        return $this->urlGenerator->generate(
            'admin_ticket_approval_edit',
            ['entityId' => $ticketApproval->getId()],
            UrlGeneratorInterface::ABSOLUTE_URL
        );
    }
}
