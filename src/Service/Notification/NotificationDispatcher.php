<?php

namespace App\Service\Notification;

use Psr\Log\LoggerInterface;
use Throwable;

/**
 * Единая точка отправки уведомлений по двум каналам (Telegram + email) с
 * экономией почты: сначала пробуем Telegram, и только если он не сработал
 * (сбой отправки ИЛИ у админа не привязан telegramChatId) — шлём email как
 * запасной канал. Раньше оба канала стреляли независимо и параллельно
 * (TechSupportListener, TechSupportMessageListener, TicketApprovalListener,
 * у каждого — свой дублирующийся try/catch) — теперь эта логика в одном месте.
 */
readonly class NotificationDispatcher
{
    public function __construct(private LoggerInterface $logger) {}

    /**
     * @param callable(): bool     $sendTelegram Должен вернуть true/false — успех отправки
     *                                           (Telegram-сервисы уже возвращают bool: false
     *                                           и при сбое HTTP, и при отсутствии telegramChatId).
     * @param callable(): mixed    $sendEmail
     * @param string               $label        Человекочитаемое имя события для логов (напр. "TechSupport").
     * @param array<string, mixed> $logContext Доп. контекст для логов (id сущностей и т.п.).
     */
    public function send(callable $sendTelegram, callable $sendEmail, string $label, array $logContext = []): void
    {
        $telegramSent = false;

        try {
            $telegramSent = (bool) $sendTelegram();
        } catch (Throwable $e) {
            $this->logger->error("Не удалось отправить Telegram-уведомление: {$label}", [...$logContext, 'exception' => $e]);
        }

        // Telegram прошёл (или технически не упал, а просто не пришло 200) —
        // почту не тратим, она нужна только как запасной канал.
        if ($telegramSent) return;

        try {
            $sendEmail();
        } catch (Throwable $e) {
            $this->logger->error("Не удалось отправить email-уведомление: {$label}", [...$logContext, 'exception' => $e]);
        }
    }
}
