<?php

namespace App\ApiResource\OAuth;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\Post;
use App\Controller\Api\OAuth\Google\GoogleOAuthCallbackController;
use App\Controller\Api\OAuth\Google\GoogleOAuthUrlController;
use App\Controller\Api\OAuth\Meta\Facebook\FacebokOAuthUrlController;
use App\Controller\Api\OAuth\Meta\Facebook\FacebookOAuthCallbackController;
use App\Controller\Api\OAuth\Meta\Instagram\InstagramOAuthCallbackController;
use App\Controller\Api\OAuth\Meta\Instagram\InstagramOAuthUrlController;
use App\Controller\Api\OAuth\Telegram\TelegramOAuthCallbackController;
use App\Dto\OAuth\GeneralAuthUrlOutput;
use App\Dto\OAuth\GeneralCallbackInput;
use App\Dto\OAuth\GeneralCallbackOutput;
use App\Dto\OAuth\TelegramCallbackInput;

/**
 * Роуты OAuth-ВХОДА (регистрация/логин через соцсеть) — для ссылки/отвязки
 * провайдера у УЖЕ залогиненного пользователя см. соседний ProfileOAuth.
 *
 * ДВЕ РАЗНЫЕ схемы авторизации живут под одной крышей GeneralOAuth:
 *
 * 1) Google / Facebook / Instagram — классический OAuth2 "code + state"
 *    (authorization code flow). Все три реализованы ОДИНАКОВО, через общий
 *    AbstractOAuthService/AbstractOAuthCallbackController — различаются
 *    только URI провайдера и разбором ответа. Полный код-флоу:
 *
 *      a. Фронтенд: GET /auth/{provider}/url
 *         → {Provider}OAuthUrlController (наследует AbstractOAuthUrlController)
 *         → AbstractOAuthService::generateOAuthRedirectUri():
 *             - генерит случайный $state (16 байт), кладёт в кэш
 *               ("oauth_state_{state}" → 'true', TTL 600с — см.
 *               StateStorageService) — это НЕ хранение состояния сессии,
 *               а чистая защита от подделки/повтора callback'а (доказывает,
 *               что этот code/state round-trip реально начали МЫ, а не
 *               атакующий подставил свой code на чужой аккаунт);
 *             - собирает URL согласия провайдера (getAuthUri()+getAuthParams(),
 *               реализация — в каждом конкретном *OAuthService).
 *         Фронтенд редиректит браузер на этот URL.
 *
 *      b. Пользователь подтверждает доступ на стороне провайдера → провайдер
 *         редиректит браузер обратно на фронтенд-страницу (redirect_uri из
 *         getAuthParams(), НЕ на этот бэкенд) с ?code=...&state=... в query.
 *
 *      c. Фронтенд читает code/state из URL и шлёт их JSON'ом:
 *         POST /auth/{provider}/callback, тело = GeneralCallbackInput
 *         (code, state, необязательный role — 'master'/'client')
 *         → {Provider}OAuthCallbackController (наследует
 *           AbstractOAuthCallbackController)
 *         → AbstractOAuthService::handleCode() — единая для всех трёх
 *           провайдеров реализация (см. её докблок в самом классе):
 *             1. Сверяет state с тем, что лежит в StateStorageService, и
 *                СРАЗУ удаляет его — одноразовый, повторный вызов с тем же
 *                state уже упадёт с OAUTH_INVALID_STATE.
 *             2. exchangeCodeForTokens($code) — обменивает code на
 *                access_token (+id_token у Google) у провайдера напрямую
 *                (реализация — в каждом *OAuthService::exchangeCodeForTokens()).
 *                ВАЖНО: любая 4xx-ошибка провайдера здесь тонет — ловится
 *                ClientExceptionInterface и заменяется одним и тем же
 *                статичным OAUTH_CODE_EXCHANGE_FAILED, реальная причина
 *                (протухший/уже использованный code, редирект не совпал с
 *                зарегистрированным в приложении провайдера и т.д.) нигде
 *                не логируется — см. докблок над exchangeCodeForTokens()
 *                в InstagramOAuthService для разбора типичных причин.
 *             3. fetchUserData($tokens) — тянет профиль пользователя у
 *                провайдера (Google — верифицирует id_token JWT по JWKS
 *                провайдера + добираeт телефон/пол/дату рождения отдельным
 *                вызовом; Facebook/Instagram — Graph API /me).
 *             4. findOrCreateUser($userData, $role) — см. докблоки в каждом
 *                *OAuthService: у Google/Facebook — 3 сценария (уже
 *                привязан → залогинить; есть юзер с тем же ПОДТВЕРЖДЁННЫМ
 *                email, но ещё не привязан → неявно связать; иначе — завести
 *                нового). У Instagram — только 2 (без связывания по email,
 *                потому что Instagram email вообще не отдаёт).
 *         Ответ — GeneralCallbackOutput (user, JWT token, isNew→status как
 *             ДАННЫЕ поля тела, а не реальный HTTP-код ответа — сам ответ
 *             всегда физически 200, см. докблок GeneralCallbackOutput) +
 *         HttpOnly-cookie с refresh-токеном (RefreshTokenService — тот же
 *         механизм, что и у обычного логина по паролю).
 *
 * 2) Telegram — НЕ authorization code flow вообще, а Telegram Login Widget:
 *    фронтенд встраивает виджет Telegram, тот сам показывает попап и
 *    возвращает ПОДПИСАННЫЙ пейлоад {id, first_name, last_name, username,
 *    photo_url, auth_date, hash} прямо в JS-колбэк — эндпоинта /url тут
 *    в принципе нет и не нужно. Фронтенд шлёт (уже camelCase, включая
 *    hash/authDate — обязательны с 27.08.2026) POST /auth/telegram/callback,
 *    тело — TelegramCallbackInput → TelegramOAuthCallbackController (НЕ
 *    наследует AbstractOAuthCallbackController — форма данных другая) →
 *    TelegramOAuthService::handleCallback() — см. её докблок в самом
 *    классе: подпись (hash) проверяется через TelegramHashVerifierService,
 *    тем же сервисом, что и поток привязки в ProfileOAuth.
 *
 * Общие для (1) и (2) детали:
 *   - "role" — 'master'|'client'|null, влияет только на роль ВНОВЬ
 *     создаваемого пользователя (ROLE_MASTER/ROLE_CLIENT/ROLE_USER), на уже
 *     существующего никак не действует.
 *   - Новый пользователь через OAuth сразу active=true, approved=true —
 *     в отличие от обычной саморегистрации, тут это НЕ проходит через
 *     ручное одобрение админом (провайдер уже подтвердил личность).
 *   - Идентификатор связи "наш User ↔ аккаунт провайдера" — сущность
 *     OAuthProvider (см. её докблок), у одного User может быть несколько
 *     (по одному на провайдера), но одна и та же (provider, providerId)
 *     пара — только у одного User (уникальный констрейнт на уровне БД).
 */
#[ApiResource(
    operations: [
        // Google
        new Post(
            uriTemplate: '/auth/google/callback',
            controller: GoogleOAuthCallbackController::class,
            normalizationContext: ['groups' => [
                'google:read',
                'masters:read',
                'clients:read',
                'users:me:read'
            ],
                'skip_null_values' => false
            ],
            denormalizationContext: ['groups' => ['google:write']],
            input: GeneralCallbackInput::class,
            output: GeneralCallbackOutput::class,
            read: false,
            write: false,
        ),
        new Get(
            uriTemplate: '/auth/google/url',
            controller: GoogleOAuthUrlController::class,
            input: false,
            output: GeneralAuthUrlOutput::class,
            read: false,
            write: false
        ),

        // Meta - Instagram
        new Post(
            uriTemplate: '/auth/instagram/callback',
            controller: InstagramOAuthCallbackController::class,
            normalizationContext: ['groups' => [
                'instagram:read',
                'masters:read',
                'clients:read',
                'users:me:read'
            ],
                'skip_null_values' => false
            ],
            denormalizationContext: ['groups' => ['instagram:write']],
            input: GeneralCallbackInput::class,
            output: GeneralCallbackOutput::class,
            read: false,
            write: false,
        ),
        new Get(
            uriTemplate: '/auth/instagram/url',
            controller: InstagramOAuthUrlController::class,
            input: false,
            output: GeneralAuthUrlOutput::class,
            read: false,
            write: false
        ),

        // Meta - Facebook
        new Post(
            uriTemplate: '/auth/facebook/callback',
            controller: FacebookOAuthCallbackController::class,
            normalizationContext: ['groups' => [
                'facebook:read',
                'masters:read',
                'clients:read',
                'users:me:read'
            ],
                'skip_null_values' => false
            ],
            denormalizationContext: ['groups' => ['facebook:write']],
            input: GeneralCallbackInput::class,
            output: GeneralCallbackOutput::class,
            read: false,
            write: false,
        ),
        new Get(
            uriTemplate: '/auth/facebook/url',
            controller: FacebokOAuthUrlController::class,
            input: false,
            output: GeneralAuthUrlOutput::class,
            read: false,
            write: false
        ),

        // Telgeram
        new Post(
            uriTemplate: '/auth/telegram/callback',
            controller: TelegramOAuthCallbackController::class,
            normalizationContext: ['groups' => [
                'telegram:read',
                'masters:read',
                'clients:read',
                'users:me:read'
            ],
                'skip_null_values' => false
            ],
            denormalizationContext: ['groups' => ['telegram:write']],
            input: TelegramCallbackInput::class,
            output: GeneralCallbackOutput::class,
            read: false,
            write: false,
        ),
    ]
)]
class GeneralOAuth {}
