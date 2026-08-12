<?php

namespace App\Service\Notification\Telegram;

use App\Entity\TechSupport\TicketApproval;
use App\Entity\User;
use App\Service\Notification\Abstract\AbstractTicketApprovalNotificationService;

class NotifyNewTicketApprovalTelegramBotService extends AbstractTicketApprovalNotificationService
{

    /**
     * @param User $user
     * @param TicketApproval $ticketApproval
     * @return mixed
     */
    public function sendTicketApprovalNotification(User $user, TicketApproval $ticketApproval): mixed
    {
        $telegramId = $user->getTelegramChatId();

        if (!$telegramId) return false;

        $ticket = $ticketApproval->getTicket();
        $desc   = mb_substr($ticket?->getDescription() ?? '', 0, 200);
        $desc   .= mb_strlen($ticket?->getDescription() ?? '') > 200 ? '...' : '';

        // Заполняется TicketListener при автообновлении ("Изменены поля: ...")
        // либо вручную админом при создании — пусто у по-настоящему новых тикетов.
        $note = $ticketApproval->getDescription();

        $message =
            "🔎 Услуга/объявление на проверку\n\n" .
            ($note ? "✏️ <b>{$note}</b>\n\n" : '') .
            "📌 <b>{$ticket?->getTitle()}</b>\n" .
            "📂 {$this->category($ticket)} | 💰 {$this->budget($ticket)}\n" .
            "👤 " . ($ticket?->getAuthor()?->getEmail() ?? 'Неизвестен') . "\n" .
            "📝 {$desc}\n\n" .
            "🔗 <a href='{$this->ticketApprovalAdminUrl($ticketApproval)}'>Открыть в админке</a>";

        return $this->sendTelegram($telegramId, $message);
    }
}
