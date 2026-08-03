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

        $message =
            "🆕 Новая услуга/объявление для проверки\n\n" .
            "🔗 <a href='{$this->ticketApprovalAdminUrl($ticketApproval)}'>Открыть в админке</a>";

        return $this->sendTelegram($telegramId, $message);
    }
}
