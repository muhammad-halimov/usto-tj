<?php

namespace App\Service\OAuth\Interface;

/**
 * "Внешний" контракт OAuth-провайдера code+state flow (Google/Facebook/
 * Instagram) — то, что напрямую вызывают контроллеры (см.
 * AbstractOAuthUrlController/AbstractOAuthCallbackController). Обе его
 * реализации (generateOAuthRedirectUri/handleCode) уже даны один раз в
 * AbstractOAuthService — конкретным *OAuthService остаётся реализовать
 * только "внутренние" интерфейсы ниже (TokenExchangeInterface/
 * UserDataFetcherInterface/UserManagementInterface) + getProviderName()
 * и два protected-метода (getAuthUri/getAuthParams).
 *
 * Telegram этому интерфейсу НЕ следует вовсе — у него нет ни code, ни
 * state (виджет отдаёт сразу подписанные данные пользователя), поэтому
 * TelegramOAuthService — отдельный класс со своим методом handleCallback(),
 * см. его докблок и общий код-флоу в GeneralOAuth.
 */
interface OAuthServiceInterface
{
    /**
     * Строит URL согласия провайдера (redirect_uri, client_id, scope, ...)
     * и попутно генерирует/сохраняет одноразовый anti-CSRF state — см.
     * AbstractOAuthService::generateOAuthRedirectUri().
     */
    public function generateOAuthRedirectUri(): string;

    /**
     * Полный цикл обработки callback'а от провайдера: проверка state →
     * обмен code на токены → получение профиля → find-or-create
     * пользователя → выдача JWT. Реализован один раз в
     * AbstractOAuthService::handleCode() — см. её докблок за пошаговым
     * разбором.
     *
     * @return array ['user' => User, 'token' => string, 'isNew' => bool]
     */
    public function handleCode(string $code, string $state, ?string $role): array;

    /**
     * Человекочитаемое имя — сейчас нигде не используется программно
     * (не выводится клиенту, не участвует в маршрутизации), но обязано
     * быть реализовано каждым провайдером как часть контракта — задел на
     * будущее (например, для единого лога/уведомления "вход через X").
     */
    public function getProviderName(): string;
}
