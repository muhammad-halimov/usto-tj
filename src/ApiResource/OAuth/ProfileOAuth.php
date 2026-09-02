<?php

namespace App\ApiResource\OAuth;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\Post;
use App\Controller\Api\OAuth\Profile\GetOAuthProvidersController;
use App\Controller\Api\OAuth\Profile\LinkOAuthProviderController;
use App\Controller\Api\OAuth\Profile\UnlinkOAuthProviderController;

/**
 * Роуты управления соцсетями УЖЕ ЗАЛОГИНЕННОГО пользователя (привязать
 * дополнительный провайдер / отвязать / посмотреть список) — для самого
 * входа/регистрации через соцсеть см. соседний GeneralOAuth, там же —
 * общий код-флоу code+state и Telegram-виджета.
 *
 * Требует аутентификации (все три контроллера сами делают
 * $security->getUser() + $accessService->check()) — input/output/read/write
 * всюду false, потому что эти операции не про конкретную сущность из БД
 * (у ApiResource тут нет своей таблицы вообще, GeneralOAuth/ProfileOAuth —
 * пустые классы-контейнеры для маршрутов, вся логика — в контроллерах).
 *
 *   POST   /profile/oauth/link            — LinkOAuthProviderController.
 *     Тело зависит от provider (см. её докблок): для google/facebook/
 *     instagram — {provider, code, state} (тот же state, что породил
 *     /auth/{provider}/url — namespace state ОБЩИЙ с логин-потоком в
 *     GeneralOAuth, разница только в том, КУДА фронтенд отправит
 *     полученный code/state); для telegram — сырые поля виджета
 *     {provider:'telegram', id, hash, auth_date, first_name?, ...} —
 *     подпись (hash) проверяется через TelegramHashVerifierService
 *     (verifyTelegramHash() здесь и TelegramOAuthService::handleCallback()
 *     в /auth/telegram/callback — логин — используют один и тот же сервис
 *     с 27.08.2026, см. его докблок).
 *
 *   DELETE /profile/oauth/unlink/{provider} — UnlinkOAuthProviderController.
 *     Отказывает, если это последний способ входа (нет пароля И это
 *     единственный привязанный провайдер) — иначе пользователь потерял бы
 *     доступ к своему же аккаунту.
 *
 *   GET    /profile/oauth/providers        — GetOAuthProvidersController.
 *     Список привязанных провайдеров с датой привязки, без токенов/секретов.
 */
#[ApiResource(
    operations: [
        new Post(
            uriTemplate: '/profile/oauth/link',
            controller: LinkOAuthProviderController::class,
            input: false,
            output: false,
            read: false,
            write: false,
        ),
        new Delete(
            uriTemplate: '/profile/oauth/unlink/{provider}',
            controller: UnlinkOAuthProviderController::class,
            input: false,
            output: false,
            read: false,
            write: false,
        ),
        new Get(
            uriTemplate: '/profile/oauth/providers',
            controller: GetOAuthProvidersController::class,
            input: false,
            output: false,
            read: false,
            write: false,
        ),
    ]
)]
class ProfileOAuth {}
