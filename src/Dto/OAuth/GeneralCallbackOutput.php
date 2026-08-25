<?php

namespace App\Dto\OAuth;

use App\Entity\User;
use Symfony\Component\Serializer\Attribute\Groups;

/**
 * Output-DTO для POST /auth/{provider}/callback — используется всеми
 * четырьмя провайдерами (Google/Facebook/Instagram через
 * AbstractOAuthCallbackController, Telegram напрямую в
 * TelegramOAuthCallbackController), поэтому Groups включают все четыре
 * *:read + профильные (masters/clients/users:me) группы.
 */
final class GeneralCallbackOutput
{
    #[Groups([
        'google:read',
        'instagram:read',
        'facebook:read',
        'telegram:read',
        'masters:read',
        'clients:read',
        'users:me:read'
    ])]
    public ?User $user = null;

    #[Groups([
        'google:read',
        'instagram:read',
        'facebook:read',
        'telegram:read',
        'masters:read',
        'clients:read',
        'users:me:read'
    ])]
    public ?string $token = null;

    #[Groups([
        'google:read',
        'instagram:read',
        'facebook:read',
        'telegram:read',
        'masters:read',
        'clients:read',
        'users:me:read'
    ])]
    public ?string $message = null;

    /**
     * ВАЖНО: это ПОЛЕ данных, а не реальный HTTP-статус-код ответа —
     * контроллеры выставляют его в 204/200 по $result['isNew'] (см.
     * AbstractOAuthCallbackController::__invoke() и
     * TelegramOAuthCallbackController::__invoke()), но сам HTTP-ответ
     * физически ВСЕГДА возвращается с кодом 200 (через $this->json(...)
     * без явного setStatusCode). Фронтенду нужно читать именно это поле
     * из тела ответа, а не полагаться на код ответа сервера.
     */
    #[Groups([
        'google:read',
        'instagram:read',
        'facebook:read',
        'telegram:read',
        'masters:read',
        'clients:read',
        'users:me:read'
    ])]
    public ?int $status = null;
}
