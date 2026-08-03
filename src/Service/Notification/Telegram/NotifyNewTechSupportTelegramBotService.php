<?php

namespace App\Service\Notification\Telegram;

use App\Entity\TechSupport\TechSupport;
use App\Entity\User;
use App\Service\Notification\Abstract\AbstractTechSupportNotificationService;

/**
 * Telegram-канал уведомлений о тикетах ТП.
 *
 * Если telegramChatId не задан — уведомление не отправляется тихо.
 *
 * ENV:
 *   TELEGRAM_BOT_TOKEN — токен бота
 *   TELEGRAM_API_URL   — https://api.telegram.org
 */
class NotifyNewTechSupportTelegramBotService extends AbstractTechSupportNotificationService
{
    public function sendTechSupportNotification(User $user, TechSupport $techSupport): bool
    {
        $telegramId = $user->getTelegramChatId();

        if (!$telegramId) return false;

        $desc = mb_substr($techSupport->getDescription(), 0, 30) . '...';
        $imgs = $techSupport->getTechSupportMessages()->first()
            ? $techSupport->getTechSupportMessages()->first()->getImages()->count()
            : 0;

        $message =
            "🆕 Новая заявка в ТП\n\n" .
            "📌 <b>{$techSupport->getTitle()}</b>\n" .
            "📂 {$this->reason($techSupport)} | 📊 {$this->status($techSupport)} | ⚡ {$this->priority($techSupport)}\n" .
            "👤 " . ($techSupport->getAuthor()?->getEmail() ?? $techSupport->getGuestEmail() ?? 'Гость') . "\n" .
            "📝 {$desc}\n" .
            "💬 {$techSupport->getTechSupportMessages()->count()} сообщ. | 🖼 {$imgs} фото\n\n" .
            "🔗 <a href='{$this->techSupportAdminUrl($techSupport)}'>Открыть в админке</a>";

        return $this->sendTelegram($telegramId, $message);
    }
}
