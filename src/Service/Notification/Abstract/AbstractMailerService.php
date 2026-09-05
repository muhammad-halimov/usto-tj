<?php

namespace App\Service\Notification\Abstract;

use Psr\Log\LoggerInterface;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\Mailer;
use Symfony\Component\Mailer\Transport;
use Symfony\Component\Mime\Email;
use Symfony\Contracts\Service\Attribute\Required;

/**
 * Базовый класс для email-сервисов.
 *
 * Инкапсулирует общий паттерн:
 *   - Создание Email (from/to/subject/text/html)
 *   - Заголовки X-Mailer и X-Entity-Ref-ID
 *   - Синхронная отправка через Transport::fromDsn
 *   - Доступ к ENV-переменным MAILER_SENDER, MAILER_DSN, FRONTEND_URL
 */
abstract class AbstractMailerService
{
    protected LoggerInterface $logger;

    /**
     * Setter-injection (как у AbstractApiHelperController::setBaseDependencies()),
     * а не конструктор: у 4 конкретных наследников (NotifyNewTicketApproval*,
     * NotifyNewTechSupport*, AccountConfirmationService,
     * AccountChangePasswordService) УЖЕ есть свои конструкторы — добавление
     * параметра сюда через __construct() потребовало бы прокидывать его
     * через все их parent::__construct() вручную. Symfony вызывает это
     * автоматически после создания сервиса благодаря #[Required].
     */
    #[Required]
    public function setLogger(LoggerInterface $logger): void
    {
        $this->logger = $logger;
    }

    protected function siteName(): string { return $_ENV['FRONTEND_URL']; }

    protected function htmlEmail(string $title, string $body, string $footer): string
    {
        return '<!DOCTYPE html><html lang="ru-RU"><head><meta charset="UTF-8"></head><body>'
            . '<div style="font-family:Arial,sans-serif;max-width:600px;margin:0 auto;color:#333">'
            . "<h1 style=\"color:#667eea\">{$title}</h1>"
            . $body
            . '<hr style="margin:30px 0;border:none;border-top:1px solid #eee">'
            . "<p style=\"color:#999;font-size:12px\">{$footer}</p>"
            . '</div></body></html>';
    }

    protected function htmlButton(string $href, string $label): string
    {
        return '<p style="margin:30px 0">'
            . "<a href=\"{$href}\" style=\"background:linear-gradient(135deg,#667eea,#764ba2);color:white;"
            . 'padding:15px 30px;text-decoration:none;border-radius:50px;display:inline-block;font-weight:600">'
            . $label
            . '</a></p>';
    }

    /**
     * @throws TransportExceptionInterface
     */
    protected function sendEmail(
        string $to,
        string $subject,
        string $text,
        string $html,
        string $refId,
    ): string {
        $email = (new Email())
            ->from($_ENV['MAILER_SENDER'])
            ->to($to)
            ->subject($subject)
            ->text($text)
            ->html($html);

        $headers = $email->getHeaders();
        $headers->addTextHeader('X-Mailer', 'Symfony Mailer');
        $headers->addTextHeader('X-Entity-Ref-ID', $refId);

        new Mailer(Transport::fromDsn($_ENV['MAILER_DSN']))->send($email);

        return "Письмо отправлено {$to}";
    }

    /**
     * БАГФИКС (05.09.2026, по жалобе "не приходит уведомление о новом
     * объявлении"): раньше при неуспехе (код ответа Telegram — не 200)
     * метод просто молча возвращал false — ни разу не логировался ни
     * реальный HTTP-код, ни тело ответа Telegram (там обычно понятная
     * причина: "chat not found" — бот заблокирован получателем, невалидный
     * chat_id и т.п. — или "Unauthorized" — протух/неверный
     * TELEGRAM_BOT_TOKEN). Тот же класс проблемы, что уже чинили у
     * Instagram OAuth (см. InstagramOAuthService) — причина сбоя терялась
     * полностью, из логов нельзя было понять, что вообще произошло.
     *
     * @param string $chatId
     * @param string $message
     * @return bool
     */
    protected function sendTelegram(string $chatId, string $message): bool
    {
        $ch = curl_init("{$_ENV['TELEGRAM_API_URL']}/bot{$_ENV['TELEGRAM_BOT_TOKEN']}/sendMessage");

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POSTFIELDS     => http_build_query([
                'chat_id' => $chatId,
                'text' => $message,
                'parse_mode' => 'HTML',
                'disable_web_page_preview' => true
            ]),
            CURLOPT_POST           => 1,
            CURLOPT_TIMEOUT        => 10,
        ]);

        $response = curl_exec($ch);
        $curlErrno = curl_errno($ch);
        $curlError = curl_error($ch);
        $httpCode  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200) {
            $this->logger->error('Telegram: sendMessage не удался', [
                'chatId'    => $chatId,
                'httpCode'  => $httpCode,
                'response'  => $response,
                'curlErrno' => $curlErrno,
                'curlError' => $curlError,
            ]);
        }

        return $httpCode === 200;
    }
}
