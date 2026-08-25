<?php

namespace App\Service\OAuth\Interface;

/**
 * Второй шаг code+state flow (см. код-флоу в GeneralOAuth) — реализуется
 * каждым *OAuthService отдельно (у каждого провайдера свой token endpoint
 * и свой формат тела запроса — Google/Instagram шлют POST
 * x-www-form-urlencoded, Facebook почему-то GET с теми же полями в query,
 * см. её exchangeCodeForTokens()), но у всех троих одна и та же дыра:
 * ClientExceptionInterface (любая 4xx от провайдера) глушится и заменяется
 * ОДНИМ статичным сообщением OAUTH_CODE_EXCHANGE_FAILED — реальная причина
 * (code уже использован/протух, redirect_uri не совпал с зарегистрированным
 * у провайдера, неверный client_secret и т.п.) нигде не логируется.
 */
interface TokenExchangeInterface
{
    /**
     * Меняет одноразовый authorization code на access_token (и у Google —
     * дополнительно id_token, JWT с данными пользователя).
     *
     * @return array Сырой ответ провайдера как есть (форма своя у каждого)
     */
    public function exchangeCodeForTokens(string $code): array;
}
