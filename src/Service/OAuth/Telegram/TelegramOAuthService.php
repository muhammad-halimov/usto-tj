<?php

namespace App\Service\OAuth\Telegram;

use App\ApiResource\AppMessages;
use App\Dto\OAuth\TelegramCallbackInput;
use App\Entity\Extra\OAuthProvider;
use App\Entity\User;
use App\Exception\AppMessageException;
use App\Repository\User\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\DecodingExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Telegram-логин — СТРУКТУРНО другой флоу, чем у Google/Facebook/
 * Instagram (см. код-флоу в GeneralOAuth): нет code/state, нет /url
 * эндпоинта, нет обмена authorization code. Вместо этого Telegram Login
 * Widget (JS-виджет на фронте) сам получает подписанные данные
 * пользователя и напрямую отдаёт их в JS-колбэк — фронтенд просто
 * пересылает эти поля сюда, в /auth/telegram/callback
 * (TelegramOAuthCallbackController → handleCallback() ниже).
 *
 * Этот класс НЕ наследует AbstractOAuthService и не реализует ни один из
 * OAuth*Interface — он самодостаточен, т.к. флоу принципиально не
 * укладывается в схему "code+state" (нет этих двух сущностей вовсе).
 *
 * ============================================================
 * ВНИМАНИЕ, ПОТЕНЦИАЛЬНАЯ УЯЗВИМОСТЬ (не исправлено, только описано):
 * handleCallback() НЕ проверяет HMAC-подпись (поле hash), которую
 * Telegram Login Widget обязан присылать вместе с данными — единственная
 * проверка здесь это checkTelegramUserExists() (см. ниже), которая лишь
 * подтверждает, что numeric id соответствует РЕАЛЬНОМУ Telegram-аккаунту,
 * но НИКАК не доказывает, что запрос на самом деле пришёл от владельца
 * этого id. Т.е. теоретически, зная (или подобрав) чужой Telegram id,
 * можно залогиниться ИМ через этот эндпоинт.
 *
 * Для сравнения: LinkOAuthProviderController::verifyTelegramHash()
 * (используется в ДРУГОМ флоу — привязка провайдера у уже залогиненного
 * юзера, POST /profile/oauth/link) делает это ПРАВИЛЬНО — пересчитывает
 * hash_hmac('sha256', $dataCheckString, hash('sha256', BOT_TOKEN, true))
 * и сверяет с присланным hash, плюс проверяет auth_date на свежесть (не
 * старше 600 сек). Тот же самый алгоритм проверки стоило бы добавить и
 * сюда, в handleCallback(), прежде чем доверять $id/$username/... —
 * поля password/token в реальности начинаются с полей, присылаемых
 * Telegram-виджетом, а не с проверенного сервером источника.
 * ============================================================
 */
readonly class TelegramOAuthService
{
    public function __construct(
        private UserRepository           $userRepository,
        private EntityManagerInterface   $entityManager,
        private JWTTokenManagerInterface $jwtManager,
        private HttpClientInterface      $httpClient,
    ){}

    /**
     * Точка входа флоу логина через Telegram — см. предупреждение о
     * непроверенной подписи в докблоке класса выше.
     *
     * @throws TransportExceptionInterface
     * @throws ServerExceptionInterface
     * @throws RedirectionExceptionInterface
     * @throws DecodingExceptionInterface
     * @throws ClientExceptionInterface
     */
    public function handleCallback(int $id, ?string $username, ?string $firstName, ?string $lastName, ?string $photoUrl, ?string $role): array
    {
        // Подтверждает только "этот id — реальный Telegram-аккаунт",
        // НЕ "этот запрос действительно от владельца аккаунта" (нет
        // проверки hash) — см. докблок класса.
        if (!$this->checkTelegramUserExists($id)) {
            throw new AppMessageException(AppMessages::USER_NOT_FOUND);
        }

        $input            = new TelegramCallbackInput();
        $input->id        = $id;
        $input->username  = $username;
        $input->firstName = $firstName;
        $input->lastName  = $lastName;
        $input->photoUrl  = $photoUrl;

        return $this->findOrCreateUser($input, $role ?? 'user');
    }

    /**
     * Двухступенчатый find-or-create (как у Instagram — Telegram тоже не
     * даёт email, только id/username/имя/фамилию/аватар).
     */
    private function findOrCreateUser(TelegramCallbackInput $telegramData, string $role): array
    {
        $telegramId = (string) $telegramData->id;

        // 1. Already linked — log in directly
        if ($user = $this->userRepository->findByOAuthProvider('telegram', $telegramId)) {
            $this->updateUserFromTelegramData($user, $telegramData);
            $this->entityManager->flush();
            return ['user' => $user, 'token' => $this->jwtManager->create($user), 'isNew' => false];
        }

        // 2. New user — create immediately with a local placeholder email
        $user = (new User())
            ->setEmail("oauth+telegram_{$telegramId}@internal.local")
            ->setName($telegramData->firstName ?? '')
            ->setSurname($telegramData->lastName ?? '')
            ->setLogin($telegramData->username ?? null)
            ->setImageExternalUrl($telegramData->photoUrl ?? '')
            ->setPassword('')
            ->setActive(true)
            ->setApproved(true)
            ->setGender('gender_neutral')
            ->setRoles(match($role) {
                'master' => ['ROLE_MASTER'],
                'client' => ['ROLE_CLIENT'],
                default  => ['ROLE_USER'],
            });

        $op = (new OAuthProvider())
            ->setProvider('telegram')
            ->setProviderId($telegramId)
            ->setUser($user);

        $this->entityManager->persist($user);
        $this->entityManager->persist($op);
        $this->entityManager->flush();

        return ['user' => $user, 'token' => $this->jwtManager->create($user), 'isNew' => true];
    }

    private function updateUserFromTelegramData(User $user, TelegramCallbackInput $telegramData): void
    {
        if ($telegramData->username !== null && empty($user->getLogin())) {
            $user->setLogin($telegramData->username);
        }
        if ($telegramData->firstName !== null && empty($user->getName())) {
            $user->setName($telegramData->firstName);
        }
        if ($telegramData->lastName !== null && empty($user->getSurname())) {
            $user->setSurname($telegramData->lastName);
        }
        if ($telegramData->photoUrl !== null && empty($user->getImageExternalUrl())) {
            $user->setImageExternalUrl($telegramData->photoUrl);
        }
    }

    /**
     * @throws TransportExceptionInterface
     * @throws ServerExceptionInterface
     * @throws RedirectionExceptionInterface
     * @throws DecodingExceptionInterface
     * @throws ClientExceptionInterface
     */
    /**
     * Живой запрос к Bot API (getChat) — единственная проверка, что id
     * реальный. НЕ проверяет, что запрос пришёл от владельца id (нет
     * HMAC-проверки, см. докблок класса) — это отдельная и куда более
     * важная проверка, отсутствующая здесь.
     */
    private function checkTelegramUserExists(int $userId): bool
    {
        try {
            $data = $this->httpClient->request(
                'GET',
                "https://api.telegram.org/bot{$_ENV['TELEGRAM_BOT_TOKEN']}/getChat",
                ['query' => ['chat_id' => $userId]]
            )->toArray(false);

            return $data['ok'] ?? false;
        } catch (Exception) {
            return false;
        }
    }
}
