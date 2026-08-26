<?php

namespace App\EventListener;

use App\ApiResource\AppMessages;
use App\Entity\User;
use App\Exception\AppMessageException;
use App\Repository\User\UserRepository;
use App\Service\Auth\AccountConfirmationService;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsEntityListener;
use Doctrine\ORM\Events;
use Psr\Log\LoggerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Throwable;

/**
 * Автоматически хэширует пароль пользователя перед записью в БД, и
 * триггерит письмо-подтверждение аккаунта для свежесозданных
 * пользователей, которым оно ещё нужно (см. postPersist() ниже).
 *
 * Зачем слушатель, а не логика в контроллере?
 *   Пароль может быть установлен из разных мест: регистрация, смена пароля,
 *   OAuth-создание аккаунта, команды консоли, фикстуры. Listener гарантирует,
 *   что plaintext никогда не попадёт в БД независимо от точки входа. Та же
 *   логика применима и к письму-подтверждению: один choke-point на ВСЕ
 *   точки создания User (обычная саморегистрация, EasyAdmin), а не
 *   отдельный вызов, который легко забыть добавить в новый контроллер.
 */
#[AsEntityListener(event: Events::prePersist, entity: User::class)]
#[AsEntityListener(event: Events::preUpdate, entity: User::class)]
#[AsEntityListener(event: Events::postPersist, entity: User::class)]
readonly class UserListener
{
    /**
     * Префиксы уже захэшированных паролей.
     *   $2y$, $2a$, $2b$ — bcrypt (PHP по умолчанию использует $2y$)
     *   $argon2 — Argon2i / Argon2id
     * Если пароль начинается с одного из них — он уже хэширован, пропускаем.
     */
    private const array HASHED_PASSWORD_PREFIX = ['$2y$', '$argon2', '$2a$', '$2b$'];

    public function __construct(
        private UserPasswordHasherInterface  $passwordHasher,
        private AccountConfirmationService   $accountConfirmationService,
        private UserRepository               $userRepository,
        private LoggerInterface              $logger,
    ) {}

    /**
     * Хэшируем пароль и проверяем уникальность email/login перед
     * сохранением пользователя
     */
    public function prePersist(User $user): void
    {
        $this->checkUniqueness($user);
        $this->hashPasswordIfNeeded($user);
    }

    /**
     * То же самое перед обновлением — email/login могут поменять и
     * через PATCH /users/{id}, дубликат должен ловиться и там же
     */
    public function preUpdate(User $user): void
    {
        $this->checkUniqueness($user);
        $this->hashPasswordIfNeeded($user);
    }

    /**
     * БАГФИКС (26.08.2026, системный фикс формата ошибок — см. докблок
     * AppMessageException): раньше уникальность email/login проверялась
     * через #[UniqueEntity] прямо на классе User — рабочий, но отдающий
     * ДРУГОЙ формат ответа (ConstraintViolationList, 422,
     * нелокализованный английский текст), чем весь остальной API
     * ({code, message} через AppMessages). Перенесено сюда, чтобы кидать
     * AppMessageException и получить тот же формат, что и везде.
     *
     * Сравниваем по id, а не просто "нашли ли что-то": на preUpdate
     * findOneBy() с НЕизменённым email/login находит самого же
     * редактируемого пользователя — это не дубликат, а он сам.
     * На prePersist $user->getId() ещё null, так что любая найденная
     * строка — чужая, сравнение отработает верно и без спецкейса.
     */
    private function checkUniqueness(User $user): void
    {
        $email = $user->getEmail();
        if ($email !== null) {
            $existing = $this->userRepository->findOneBy(['email' => $email]);
            if ($existing !== null && $existing->getId() !== $user->getId()) {
                throw new AppMessageException(AppMessages::EMAIL_ALREADY_EXISTS);
            }
        }

        $login = $user->getLogin();
        if ($login !== null) {
            $existing = $this->userRepository->findOneBy(['login' => $login]);
            if ($existing !== null && $existing->getId() !== $user->getId()) {
                throw new AppMessageException(AppMessages::LOGIN_ALREADY_EXISTS);
            }
        }
    }

    /**
     * Отправляет письмо-подтверждение аккаунта сразу после создания
     * пользователя — но только если он ещё НЕ active. Все пути, где
     * подтверждение не нужно (OAuth-логин/регистрация — Google/Facebook/
     * Instagram/TelegramOAuthService, CreateAdminCommand), сами явно
     * ставят active=true ДО persist() — им сюда попадать не нужно, у них
     * личность уже подтверждена провайдером/консолью. Обычная
     * саморегистрация (POST /users — у ApiResource нет своего
     * контроллера, значит active остаётся дефолтным false из
     * User::$active) и создание пользователя из EasyAdmin (если admin
     * оставил галочку active снятой) — оба этих случая сюда попадают.
     *
     * postPersist, а не prePersist: AccountConfirmationService создаёт
     * AccountConfirmationToken со ссылкой на этого же User и сама делает
     * persist()+flush() — для этого нужен уже реальный id пользователя, а
     * Doctrine назначает его только после фактического INSERT, то есть
     * не раньше postPersist. Тот же паттерн (собственный persist()+
     * flush() из postXxx-хука), что уже использует TicketApprovalListener.
     *
     * Ошибка отправки (SMTP временно недоступен и т.п.) не должна валить
     * саму регистрацию 500-кой — только логируем и продолжаем,
     * пользователь всегда может запросить письмо повторно через
     * POST /confirm-account-tokenless/.
     */
    public function postPersist(User $user): void
    {
        if ($user->getActive()) return;

        try {
            $this->accountConfirmationService->sendConfirmationEmail($user);
        } catch (Throwable $e) {
            $this->logger->error('Не удалось отправить письмо подтверждения аккаунта при регистрации', [
                'userId'    => $user->getId(),
                'email'     => $user->getEmail(),
                'exception' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Хэширует пароль, если он еще не хэширован
     */
    private function hashPasswordIfNeeded(User $user): void
    {
        $password = $user->getPassword();

        if (!$password || $this->isPasswordHashed($password)) {
            return;
        }

        $hashedPassword = $this->passwordHasher->hashPassword($user, $password);
        $user->setPassword($hashedPassword);
    }

    /**
     * Проверяет, хэширован ли пароль
     */
    private function isPasswordHashed(string $password): bool
    {
        return array_any(self::HASHED_PASSWORD_PREFIX, fn($prefix) => str_starts_with($password, $prefix));
    }
}
