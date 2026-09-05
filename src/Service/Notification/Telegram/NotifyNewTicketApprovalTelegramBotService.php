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
        // Telegram HTML-режим не рендерит "<br>" — разделитель строк "\n",
        // в отличие от email/EasyAdmin. Запас на длину строки одной правки
        // всё равно оставляем (описание тикета может быть длинным само по себе).
        //
        // isCreationOnly() (05.09.2026, по жалобе "это сейчас просто
        // модифицированное редактирование"): для только что созданного
        // тикета блок "Изменения" не несёт смысла — он показывал бы
        // "(пусто) → значение" по каждому полю, хотя объявление просто ещё
        // ни разу не публиковалось, а не редактировалось. Всё содержимое и
        // так есть ниже, в фиксированной сводке (заголовок/категория/
        // бюджет/описание) — блок целиком скрываем и меняем заголовок.
        $isNew = $ticketApproval->isCreationOnly();

        $changes = $ticketApproval->getLatestChangeSummary("\n");
        $changes = mb_substr($changes, 0, 1000) . (mb_strlen($changes) > 1000 ? '…' : '');

        // Разделитель + подписанные поля вместо "📂 X | 💰 Y" в одну строку
        // (05.09.2026, по жалобе "плохо выглядит") — та же информация,
        // просто в столбик и с явными подписями, чтобы не приходилось
        // расшифровывать порядок по значкам.
        $divider = '▫️▫️▫️▫️▫️▫️▫️▫️▫️▫️▫️▫️▫️▫️';
        $adminUrl = $this->ticketApprovalAdminUrl($ticketApproval);

        $message =
            ($isNew ? "🆕 <b>Новое объявление на проверку</b>\n" : "🔎 <b>Услуга/объявление на проверку</b>\n") .
            "{$divider}\n\n" .
            (!$isNew && $changes !== '—' ? "✏️ <b>Изменения:</b>\n{$changes}\n\n" : '') .
            "📌 <b>{$ticket?->getTitle()}</b>\n\n" .
            "📂 Категория: {$this->category($ticket)}\n" .
            "💰 Бюджет: {$this->budget($ticket)}\n" .
            "👤 Автор: {$this->owner($ticket)}\n\n" .
            "📝 {$desc}\n\n" .
            "{$divider}\n" .
            "🔗 <a href='{$adminUrl}'>Открыть в админке</a>";

        // Кнопки под сообщением (05.09.2026, по просьбе "добавь две кнопки,
        // открыть админку и подтвердить") — ссылка выше в тексте ("🔗
        // Открыть в админке") специально ОСТАВЛЕНА как есть, кнопки — это
        // ДОПОЛНИТЕЛЬНЫЙ, более удобный на телефоне способ сделать то же
        // самое + одобрить прямо из Telegram одним тапом, не заходя в
        // админку. Callback "approve_ticket_approval:{id}" обрабатывается в
        // TelegramBotController::webhook() — там же проверка прав (только
        // ROLE_ADMIN по telegram_chat_id) и сам вызов
        // TicketApproval::setApproved(true) (тот же метод, что и
        // переключатель в EasyAdmin/batchApprove — вся защитная логика
        // setApproved() — банальный тикет и т.п. — работает так же).
        $keyboard = [[
            ['text' => '🔗 Админке', 'url' => $adminUrl],
            ['text' => '✅ Подтвердить', 'callback_data' => "approve_ticket_approval:{$ticketApproval->getId()}"],
        ]];

        return $this->sendTelegram($telegramId, $message, $keyboard);
    }
}
