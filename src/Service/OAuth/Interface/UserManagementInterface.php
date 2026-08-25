<?php

namespace App\Service\OAuth\Interface;

use App\Entity\User;

/**
 * Последний шаг code+state flow (см. код-флоу в GeneralOAuth) — превратить
 * профиль от провайдера в нашего User. Сама стратегия поиска/создания у
 * каждого *OAuthService СВОЯ (см. докблоки findOrCreateUser() в
 * GoogleOAuthService/FacebookOAuthService/InstagramOAuthService) — общее
 * только то, что все три пытаются переиспользовать уже существующего User
 * там, где это возможно, прежде чем завести нового.
 */
interface UserManagementInterface
{
    /**
     * Три типовых сценария (детали и исключения — в реализациях):
     *   1. providerId уже привязан (OAuthProvider) → вернуть того User.
     *   2. Есть User с тем же ПОДТВЕРЖДЁННЫМ email, но без привязки этого
     *      провайдера → неявно связать (у Instagram этого сценария нет —
     *      email провайдер не отдаёт вовсе).
     *   3. Ни то ни другое → создать нового User + OAuthProvider,
     *      сразу active=true/approved=true (провайдер уже подтвердил
     *      личность, ручное одобрение админом не требуется).
     *
     * @return array ['user' => User, 'isNew' => bool]
     */
    public function findOrCreateUser(array $userData, ?string $role): array;

    /**
     * Дозаполняет ТОЛЬКО пустые поля уже существующего User свежими
     * данными от провайдера (имя/фамилия/аватар/...) — никогда не
     * перезатирает то, что пользователь уже сам поменял в профиле после
     * первого входа. Вызывается и при повторном логине (сценарий 1 выше),
     * и сразу после неявной привязки по email (сценарий 2).
     */
    public function updateUserData(User $user, array $userData): void;
}
