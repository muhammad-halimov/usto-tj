<?php

namespace App\Service\Extra;

use App\ApiResource\AppMessages;
use App\Entity\User;
use App\Exception\AppMessageException;
use App\Repository\User\BlackListRepository;
use Symfony\Bundle\SecurityBundle\Security;

/**
 * Централизованная проверка прав доступа для контроллеров.
 *
 * Уровни проверки ($grade):
 *   'triple' — Admin | Master | Client (по умолчанию)
 *   'double' — Master | Client
 *   'client' — только Client
 *   'master' — только Master
 *   'admin'  — только Admin
 *
 * При ошибке бросается AppMessageException (см. её докблок) — HTTP-код
 * берётся из AppMessages::REGISTRY по коду ошибки, а не из типа
 * исключения. БАГФИКС (26.08.2026, системный фикс формата ошибок): до
 * этого здесь кидались голые TokenNotFoundException/
 * AccessDeniedHttpException(AppMessages::get(...)->message) — это
 * центральная точка проверки доступа буквально ВСЕХ защищённых
 * эндпоинтов, так что баг "code теряется, долетает только текст" отсюда
 * бил почти по всему API разом (401/403 без code).
 */
readonly class AccessService
{
    // Роли, которые считаются "полнымии участниками" (см. grade='triple'/'double')
    private const array TRIPLE_ALLOWED_ROLES = ['ROLE_ADMIN', 'ROLE_CLIENT', 'ROLE_MASTER'];
    private const array DOUBLE_ALLOWED_ROLES = ['ROLE_CLIENT', 'ROLE_MASTER'];
    private const array SINGLE_ALLOWED_ROLES = ['ROLE_USER', 'ROLE_ADMIN', 'ROLE_CLIENT', 'ROLE_MASTER'];

    public function __construct(
        private Security            $security,
        private BlackListRepository $blackListRepository,
    ){}

    /**
     * Проверяет аутентификацию, активность и роль пользователя.
     *
     * @param User|null $user            Текущий пользователь из Security
     * @param string    $grade           Уровень проверки роли (triple/double/client/master/admin)
     * @param bool      $activeAndApproved Требовать ли active=true и approved=true
     *                                    (отключается false только в спец. эндпоинтах,
     *                                    например при повторной отправке письма подтверждения)
     */
    public function check(User|null $user, string $grade = 'triple', bool $activeAndApproved = true) : bool
    {
        if ($grade === 'anonymous') return true;

        if (!$user)
            throw new AppMessageException(AppMessages::AUTHENTICATION_REQUIRED);
        elseif (!$this->security->isGranted('IS_AUTHENTICATED_FULLY'))
            throw new AppMessageException(AppMessages::AUTHENTICATION_REQUIRED);
        elseif (!$this->security->getUser())
            throw new AppMessageException(AppMessages::AUTHENTICATION_REQUIRED);

        // ROLE_SUPER_ADMIN всемогущ: проходит любой $grade (включая 'client'/
        // 'master'-only) и не блокируется active/approved/banned. Единственное,
        // что выше уже проверено — что это реально аутентифицированный пользователь.
        if (in_array('ROLE_SUPER_ADMIN', $user->getRoles(), true)) {
            return true;
        }

        // Бан — жёсткая блокировка без исключений (кроме ROLE_SUPER_ADMIN выше).
        // Срабатывает независимо от $activeAndApproved: этот флаг отключается
        // только для спец-эндпоинтов вроде повторной отправки письма
        // подтверждения (см. докблок параметра) — то "мягкое" условие про ещё
        // не подтверждённый email, а не про блокировку. Бан — другое, отключать
        // его для конкретных эндпоинтов смысла нет.
        if ($user->getBanned()) {
            throw new AppMessageException(AppMessages::ACCESS_DENIED);
        }

        if ($activeAndApproved) {
            if (!$user->getActive())
                throw new AppMessageException(AppMessages::ACCESS_DENIED);
            elseif (!$user->getApproved())
                throw new AppMessageException(AppMessages::ACCESS_DENIED);
        }

        switch ($grade) {
            case 'triple':
                if (!array_intersect(self::TRIPLE_ALLOWED_ROLES, $user->getRoles()))
                    throw new AppMessageException(AppMessages::EXTRA_DENIED);
                break;
            case 'double':
                if (!array_intersect(self::DOUBLE_ALLOWED_ROLES, $user->getRoles()))
                    throw new AppMessageException(AppMessages::EXTRA_DENIED);
                break;
            case 'single':
                if (!array_intersect(self::SINGLE_ALLOWED_ROLES, $user->getRoles()))
                    throw new AppMessageException(AppMessages::EXTRA_DENIED);
                break;
            case 'client':
                if (!in_array("ROLE_CLIENT", $user->getRoles()))
                    throw new AppMessageException(AppMessages::EXTRA_DENIED);
                break;
            case 'master':
                if (!in_array("ROLE_MASTER", $user->getRoles()))
                    throw new AppMessageException(AppMessages::EXTRA_DENIED);
                break;
            case 'admin':
                if (!in_array("ROLE_ADMIN", $user->getRoles()))
                    throw new AppMessageException(AppMessages::EXTRA_DENIED);
                break;
            default:
                throw new AppMessageException(AppMessages::EXTRA_DENIED);
        }

        return true;
    }

    /**
     * Проверяет блокировку в чате: не заблокировал ли $recipient пользователя
     * $sender. Асимметрично — писать не может ТОЛЬКО заблокированная
     * сторона ($sender): тот, кто поставил блок ($recipient), сам может
     * продолжать переписку по своему усмотрению, ограничение накладывается
     * только на цель блокировки. Раньше проверка была симметричной (любая
     * блокировка в любую сторону останавливала обоих) — это убрано намеренно.
     */
    public function checkBlackList(?User $sender, ?User $recipient): bool
    {
        if (!$sender || !$recipient) return true;

        if ($this->blackListRepository->findDuplicate($recipient, $sender)) {
            throw new AppMessageException(AppMessages::USER_BLOCKED);
        }

        return true;
    }
}
