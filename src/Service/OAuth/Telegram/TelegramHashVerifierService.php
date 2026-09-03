<?php

namespace App\Service\OAuth\Telegram;

use Psr\Log\LoggerInterface;

/**
 * Проверка HMAC-подписи Telegram Login Widget — официальный алгоритм из
 * документации Telegram (https://core.telegram.org/widgets/login#checking-
 * authorization): собрать все присланные виджетом поля (кроме hash),
 * отсортировать по ключу, склеить в "key=value\n..." и сверить
 * HMAC-SHA256 с ключом sha256(bot_token) против присланного hash.
 * hash_equals() — намеренно constant-time сравнение (защита от
 * timing-атак), а не ===.
 *
 * БАГФИКС (27.08.2026): раньше эта проверка была реализована только в
 * LinkOAuthProviderController::verifyTelegramHash() (флоу привязки
 * провайдера) — флоу ЛОГИНА (TelegramOAuthService) вместо неё делал живой
 * запрос к Bot API (getChat), что: (1) не доказывало подлинность запроса
 * вообще (см. был докблок класса), и (2) реально ломало логин/регистрацию
 * для любого пользователя, который ни разу не писал боту — getChat для
 * приватного чата не срабатывает, пока пользователь сам не инициировал
 * диалог с ботом ("Bad Request: chat not found", подтверждённое поведение
 * Telegram Bot API, не баг конкретно этого проекта). Вынесено сюда одним
 * местом, используется теперь ОБОИМИ флоу — TelegramOAuthService (логин)
 * и LinkOAuthProviderController (привязка).
 *
 * Общий bot token — TELEGRAM_BOT_TOKEN (тот же секрет, что используется
 * для вызовов Bot API вроде getChat/sendMessage — по протоколу Telegram
 * Login Widget secret_key = SHA256(bot_token), это ровно тот же токен,
 * никакой отдельной "OAuth-секрет"-переменной Telegram не предусматривает).
 */
readonly class TelegramHashVerifierService
{
    public function __construct(private LoggerInterface $logger) {}

    /**
     * @param array<string, scalar|null> $fields Все поля, присланные виджетом,
     *                                            КРОМЕ hash (id, first_name,
     *                                            last_name, username, photo_url,
     *                                            auth_date — какие есть).
     * @param string $hash Присланная виджетом подпись.
     */
    public function verify(array $fields, string $hash): bool
    {
        $dataCheckFields = [];
        foreach ($fields as $key => $value) {
            if ($value !== null && $value !== '') {
                $dataCheckFields[$key] = (string) $value;
            }
        }

        ksort($dataCheckFields);
        $dataCheckString = implode(
            "\n",
            array_map(fn($k, $v) => "$k=$v", array_keys($dataCheckFields), $dataCheckFields)
        );

        $secretKey = hash('sha256', $_ENV['TELEGRAM_BOT_TOKEN'], true);
        $computedHash = hash_hmac('sha256', $dataCheckString, $secretKey);
        $isValid = hash_equals($computedHash, $hash);

        // ВРЕМЕННОЕ диагностическое логирование (добавлено 03.09.2026, по
        // жалобе "неверная подпись" в проде после деплоя фикса) — ничего
        // секретного здесь нет (dataCheckString — публичные данные профиля,
        // сам bot token не логируется), но это временно для отладки
        // конкретного случая рассинхрона, не постоянный лог на каждый
        // логин — можно убрать после того, как найдём причину.
        if (!$isValid) {
            $this->logger->warning('Telegram OAuth: подпись не совпала', [
                'dataCheckString' => $dataCheckString,
                'receivedHash'    => $hash,
                'computedHash'    => $computedHash,
                'botTokenTail'    => substr($_ENV['TELEGRAM_BOT_TOKEN'] ?? '', -6),
            ]);
        }

        return $isValid;
    }
}
