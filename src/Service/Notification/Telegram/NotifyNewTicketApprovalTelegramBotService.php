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

        // Детальный снимок "поле: было → стало" ТОЛЬКО последней правки
        // (см. SnapshotSummaryTrait::getLatestChangeSummary) — не вся
        // накопленная за окно повторного использования заявки история
        // (TicketApproval::appendSnapshot/TicketListener::resolveApproval):
        // раньше сюда уходила ПОЛНАЯ история через getSnapshotSummary(), и
        // при нескольких правках подряд в одном окне она рано или поздно
        // упиралась в защитный лимит длины и обрезалась прямо посреди
        // строки — проверено живьём. Уведомление — про "что изменилось
        // только что", полная история всё равно видна по ссылке ниже.
        // Пусто у по-настоящему новых заявок (заводятся админом вручную или
        // из TicketApprovalListener без привязки к правке). Telegram
        // HTML-режим не рендерит "<br>" — разделитель строк "\n", в отличие
        // от email/EasyAdmin. Запас на длину строки одной правки всё равно
        // оставляем (описание тикета может быть длинным само по себе).
        $changes = $ticketApproval->getLatestChangeSummary("\n");
        $changes = mb_substr($changes, 0, 1000) . (mb_strlen($changes) > 1000 ? '…' : '');

        $message =
            "🔎 Услуга/объявление на проверку\n\n" .
            ($changes !== '—' ? "✏️ <b>Изменения:</b>\n{$changes}\n\n" : '') .
            "📌 <b>{$ticket?->getTitle()}</b>\n" .
            "📂 {$this->category($ticket)} | 💰 {$this->budget($ticket)}\n" .
            "👤 " . ($ticket?->getAuthor()?->getEmail() ?? 'Неизвестен') . "\n" .
            "📝 {$desc}\n\n" .
            "🔗 <a href='{$this->ticketApprovalAdminUrl($ticketApproval)}'>Открыть в админке</a>";

        return $this->sendTelegram($telegramId, $message);
    }
}
