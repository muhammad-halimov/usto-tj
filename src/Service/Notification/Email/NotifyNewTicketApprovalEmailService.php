<?php

namespace App\Service\Notification\Email;

use App\Entity\TechSupport\TicketApproval;
use App\Entity\User;
use App\Service\Notification\Abstract\AbstractTicketApprovalNotificationService;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;

class NotifyNewTicketApprovalEmailService extends AbstractTicketApprovalNotificationService
{
    /**
     * @param User $user
     * @param TicketApproval $ticketApproval
     * @return mixed
     * @throws TransportExceptionInterface
     */
    public function sendTicketApprovalNotification(User $user, TicketApproval $ticketApproval): mixed
    {
        $siteName = $this->siteName();

        return $this->sendEmail(
            to:      $user->getEmail(),
            subject: "Новая услуга/объявление для проверки | {$siteName}",
            text:    "Новая услуга/объявление для проверки | {$siteName}",
            html:    $this->htmlEmail(
                "Новая услуга/объявление для проверки",
                "Новая услуга/объявление для проверки"
                . $this->htmlButton($this->ticketApprovalAdminUrl($ticketApproval), 'Перейти в админку'),
                "Если вы не админ на {$siteName} — проигнорируйте письмо.",
            ),
            refId: (string) $ticketApproval->getId()
        );
    }
}
