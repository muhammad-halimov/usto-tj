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
        $ticket = $ticketApproval->getTicket();

        $url        = $this->ticketApprovalAdminUrl($ticketApproval);
        $siteName   = $this->siteName();
        $title      = htmlspecialchars($ticket?->getTitle() ?? '', ENT_QUOTES, 'UTF-8');
        $desc       = htmlspecialchars($ticket?->getDescription() ?? '', ENT_QUOTES, 'UTF-8');
        $category   = $this->category($ticket);
        $budget     = $this->budget($ticket);
        $authorId   = $ticket?->getAuthor()?->getEmail() ?? 'Неизвестен';
        $author     = htmlspecialchars($authorId, ENT_QUOTES, 'UTF-8');
        // Заполняется TicketListener при автообновлении ("Изменены поля: ...")
        // либо вручную админом при создании — пусто у по-настоящему новых тикетов.
        $note       = $ticketApproval->getDescription();
        $noteHtml   = $note ? "<p style=\"color:#667eea\"><strong>{$note}</strong></p>" : '';

        return $this->sendEmail(
            to:      $user->getEmail(),
            subject: "Услуга/объявление на проверку | {$siteName}",
            text:    "Услуга/объявление на проверку\n\n{$note}\n\n{$ticket?->getTitle()}\nКатегория: {$category} | Бюджет: {$budget}\nАвтор: {$authorId}\n\n{$ticket?->getDescription()}\n\n{$url}",
            html:    $this->htmlEmail(
                'Услуга/объявление на проверку',
                $noteHtml
                . "<p>Заголовок: <strong>{$title}</strong></p>"
                . "<p>Категория: <strong>{$category}</strong> | Бюджет: <strong>{$budget}</strong></p>"
                . "<p>Автор: <strong>{$author}</strong></p>"
                . "<p style=\"color:#666;font-size:14px\">{$desc}</p>"
                . $this->htmlButton($url, 'Перейти в админку'),
                "Если вы не админ на {$siteName} — проигнорируйте письмо.",
            ),
            refId: (string) $ticketApproval->getId()
        );
    }
}
