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

        // Детальный снимок "поле: было → стало" ТОЛЬКО последней правки (см.
        // SnapshotSummaryTrait::getLatestChangeSummary) — не вся накопленная
        // за окно повторного использования заявки история (TicketApproval::
        // appendSnapshot/TicketListener::resolveApproval): письмо — про "что
        // изменилось только что", полная история всё равно видна по ссылке
        // ниже (см. тот же довод в NotifyNewTicketApprovalTelegramBotService,
        // где полная история к тому же реально упиралась в защитный лимит
        // длины и обрезалась посреди строки). Уже экранированный HTML с
        // "<br>" между строками, можно вставлять как есть. Пусто у
        // по-настоящему новых заявок (заведены не в ответ на правку тикета).
        $changes     = $ticketApproval->getLatestChangeSummary();
        $changesHtml = $changes !== '—'
            ? "<p style=\"color:#667eea\"><strong>Изменения:</strong><br>{$changes}</p>"
            : '';
        // Текстовая версия письма — getLatestChangeSummaryPlain(): то же
        // самое, но с "\n" вместо "<br>" и без HTML-экранирования.
        $changesText = $changes !== '—' ? $ticketApproval->getLatestChangeSummaryPlain() : '';

        return $this->sendEmail(
            to:      $user->getEmail(),
            subject: "Услуга/объявление на проверку | {$siteName}",
            text:    "Услуга/объявление на проверку\n\n{$changesText}\n\n{$ticket?->getTitle()}\nКатегория: {$category} | Бюджет: {$budget}\nАвтор: {$authorId}\n\n{$ticket?->getDescription()}\n\n{$url}",
            html:    $this->htmlEmail(
                'Услуга/объявление на проверку',
                $changesHtml
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
