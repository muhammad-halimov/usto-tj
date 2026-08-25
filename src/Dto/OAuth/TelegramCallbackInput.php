<?php

namespace App\Dto\OAuth;

use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Input-DTO для POST /auth/telegram/callback — поля почти 1:1 из данных,
 * которые Telegram Login Widget присылает фронту (id/username/first_name/
 * last_name/photo_url), плюс наш собственный необязательный role.
 *
 * ВНИМАНИЕ: тут НЕТ полей hash/auth_date — то есть на уровне этого DTO
 * подпись виджета даже не долетает до сервиса и не может быть проверена
 * здесь; TelegramOAuthService::handleCallback() их и не запрашивает (см.
 * подробный докблок безопасности в этом сервисе). Сравните с
 * LinkOAuthProviderController, который читает hash/auth_date из сырого
 * $request-тела вручную и ДЕЙСТВИТЕЛЬНО их проверяет.
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
}
