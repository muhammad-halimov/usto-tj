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
        // "<br>" между строками, можно вставлять как есть.
        //
        // isCreationOnly() (05.09.2026, по жалобе "это сейчас просто
        // модифицированное редактирование"): для только что созданного
        // тикета блок "Изменения" скрываем целиком — он показывал бы
        // "(пусто) → значение" по каждому полю, хотя объявление просто ещё
        // ни разу не публиковалось, а не редактировалось. Заголовок и тема
        // письма для этого случая тоже отдельные.
        $isNew       = $ticketApproval->isCreationOnly();
        $changes     = $ticketApproval->getLatestChangeSummary();
        $changesHtml = !$isNew && $changes !== '—'
            ? "<p style=\"color:#667eea\"><strong>Изменения:</strong><br>{$changes}</p>"
            : '';
        // Текстовая версия письма — getLatestChangeSummaryPlain(): то же
        // самое, но с "\n" вместо "<br>" и без HTML-экранирования.
        $changesText = !$isNew && $changes !== '—' ? $ticketApproval->getLatestChangeSummaryPlain() : '';
        $heading     = $isNew ? 'Новое объявление на проверку' : 'Услуга/объявление на проверку';

        return $this->sendEmail(
            to:      $user->getEmail(),
            subject: "{$heading} | {$siteName}",
            text:    "{$heading}\n\n{$changesText}\n\n{$ticket?->getTitle()}\nКатегория: {$category} | Бюджет: {$budget}\nАвтор: {$authorId}\n\n{$ticket?->getDescription()}\n\n{$url}",
            html:    $this->htmlEmail(
                $heading,
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
