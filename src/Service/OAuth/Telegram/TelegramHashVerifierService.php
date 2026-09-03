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
 * БАГФИКС №2 (03.09.2026, найден сразу же после первого — по жалобе
 * "неверная подпись" на все попытки логина): секрет для HMAC должен быть
 * токеном ИМЕННО того бота, что зашит в виджете на фронте
 * (data-telegram-login="ustoyobtj_auth_bot"), а НЕ TELEGRAM_BOT_TOKEN —
 * та переменная принадлежит СОВСЕМ ДРУГОМУ боту (ustoyobtj_tech_support_bot,
 * используется для уведомлений техподдержки — AbstractMailerService,
 * TelegramBotController, NotifyNewTechSupportTelegramBotService — этих
 * НЕ трогать). Два разных бота — два разных секрета, подпись виджента
 * логина никогда не могла совпасть с сверкой по чужому боту. Используем
 * отдельную переменную TELEGRAM_AUTH_BOT_TOKEN — токен ustoyobtj_auth_bot,
 * взятый у @BotFather (никакой другой источник этот токен не знает).
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

        $secretKey = hash('sha256', $_ENV['TELEGRAM_AUTH_BOT_TOKEN'], true);
        $computedHash = hash_hmac('sha256', $dataCheckString, $secretKey);
        $isValid = hash_equals($computedHash, $hash);

        // Диагностическое логирование (добавлено 03.09.2026, по жалобе
        // "неверная подпись" — причина оказалась в том, что сверяли с
        // токеном чужого бота, см. докблок класса выше). Оставлено
        // постоянно, не только на время отладки: несовпадение подписи
        // ВООБЩЕ не должно происходить у легитимных запросов после этого
        // фикса — если это снова случится (например токен бота
        // перевыпустят у @BotFather и забудут обновить .env), лог сразу
        // покажет, в чём дело, вместо повторного цикла "не работает,
        // разбираемся с нуля". Ничего секретного не логируется —
        // dataCheckString это только публичные данные Telegram-профиля,
        // сам bot token наружу не идёт (только последние 6 символов, для
        // сверки при копипасте).
        //
        // ->error(), а не ->warning(): на проде main-хендлер — fingers_crossed
        // с action_level: error (см. config/packages/monolog.yaml) — он
        // копит всё, что ниже этого уровня, в буфере на время запроса и
        // МОЛЧА ВЫБРАСЫВАЕТ буфер, если в запросе не случилось ничего
        // уровня error. warning() тут никогда бы не долетел до stderr —
        // ровно поэтому лог был пуст при первой попытке диагностики.
        if (!$isValid) {
            $this->logger->error('Telegram OAuth: подпись не совпала', [
                'dataCheckString' => $dataCheckString,
                'receivedHash'    => $hash,
                'computedHash'    => $computedHash,
                'botTokenTail'    => substr($_ENV['TELEGRAM_AUTH_BOT_TOKEN'] ?? '', -6),
            ]);
        }

        return $isValid;
    }
}
