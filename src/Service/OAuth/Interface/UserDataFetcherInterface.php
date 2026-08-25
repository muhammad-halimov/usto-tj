<?php

namespace App\Service\OAuth\Interface;

/**
 * Третий шаг code+state flow (см. код-флоу в GeneralOAuth) — по токену(ам)
 * из TokenExchangeInterface получить профиль пользователя. Форма
 * возвращаемого массива у каждого провайдера СВОЯ (Google: декодированные
 * claims id_token — sub/email/given_name/...; Facebook/Instagram: сырой
 * JSON Graph API /me по запрошенным fields) — единого формата нет, каждый
 * *OAuthService::findOrCreateUser() знает, как читать именно СВОЙ формат.
 */
interface UserDataFetcherInterface
{
    /**
     * @param array $tokens Результат exchangeCodeForTokens() — минимум
     *                       access_token, у Google ещё и id_token
     * @return array Профиль пользователя в формате конкретного провайдера
     */
    public function fetchUserData(array $tokens): array;
}
