<?php

namespace App\Dto\OAuth;

use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Input-DTO для POST /auth/telegram/callback — поля почти 1:1 из данных,
 * которые Telegram Login Widget присылает фронту (id/username/first_name/
 * last_name/photo_url), плюс наш собственный необязательный role.
 *
 * БАГФИКС (27.08.2026): раньше здесь НЕ было полей hash/authDate — подпись
 * виджета даже не долетала до сервиса, вместо проверки подписи
 * TelegramOAuthService делал живой запрос к Bot API (getChat), который
 * не только не проверял подлинность запроса, но и реально ломал логин
 * для любого пользователя, который ни разу не писал боту (см. докблок
 * TelegramOAuthService/TelegramHashVerifierService). Теперь hash/authDate
 * обязательны и проверяются тем же алгоритмом, что уже использовал
 * LinkOAuthProviderController для флоу привязки — см.
 * TelegramHashVerifierService.
 */
final class TelegramCallbackInput
{
    #[Groups(['telegram:write'])]
    #[Assert\NotBlank]
    #[Assert\Positive]
    public int $id;

    #[Groups(['telegram:write'])]
    public ?string $username = null;

    #[Groups(['telegram:write'])]
    public ?string $firstName = null;

    #[Groups(['telegram:write'])]
    public ?string $lastName = null;

    #[Groups(['telegram:write'])]
    public ?string $photoUrl = null;

    #[Groups(['telegram:write'])]
    public ?string $role = null;

    /** Подпись Telegram Login Widget — см. TelegramHashVerifierService. */
    #[Groups(['telegram:write'])]
    #[Assert\NotBlank]
    public string $hash;

    /** Unix timestamp момента авторизации в виджете — для проверки свежести (не старше 600с). */
    #[Groups(['telegram:write'])]
    #[Assert\NotBlank]
    #[Assert\Positive]
    public int $authDate;
}
