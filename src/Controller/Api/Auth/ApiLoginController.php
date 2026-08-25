<?php

namespace App\Controller\Api\Auth;

use App\ApiResource\AppMessages;
use App\Dto\ApiAuth\ApiLogin\LoginInput;
use App\Repository\User\UserRepository;
use App\Service\Auth\RefreshTokenService;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * ВНИМАНИЕ: этот контроллер сейчас НЕ подключён ни к одному роуту — его
 * Post-операция в ApiAuthentication (uriTemplate: '/authentication_token')
 * целиком закомментирована. Мёртвый код, оставлен как есть (не в рамках
 * задачи с комментариями), но при попытке его включить обратно учтите:
 * $user->getOauthType() ниже вызывает метод, КОТОРОГО НЕТ ни в User, ни
 * где-либо ещё в проекте (проверено grep'ом) — при реальном запросе это
 * упало бы фатальной ошибкой "Call to undefined method". Судя по всему
 * это заготовка под будущую фичу "OAuth-only аккаунт не может логиниться
 * по паролю" (см. OAUTH_ONLY_ACCOUNT в AppMessages), которую в итоге
 * реализовали иначе или не завершили — при реактивации либо реализуйте
 * getOauthType()/getActiveProviders()/hasAnyProvider() (например, как
 * тонкую обёртку над $user->getOauthProviders(), см. OAuthProvider), либо
 * уберите эту проверку.
 */
class ApiLoginController extends AbstractController
{
    public function __invoke(
        #[MapRequestPayload] LoginInput $input,
        UserRepository $userRepository,
        UserPasswordHasherInterface $passwordHasher,
        JWTTokenManagerInterface $jwtManager,
        RefreshTokenService $refreshTokenService
    ): JsonResponse
    {
        $user = $userRepository->findOneBy(['email' => $input->email]);

        if (!$user) throw new UnauthorizedHttpException('', AppMessages::get(AppMessages::INVALID_CREDENTIALS)->message);

        $oauthType = $user->getOauthType();

        // Проверка пароля
        if ($oauthType !== null && $oauthType->hasAnyProvider()) {
            // Пользователь зарегистрирован через OAuth - запрещаем вход по паролю
            throw new UnauthorizedHttpException('', AppMessages::get(AppMessages::OAUTH_ONLY_ACCOUNT)->message . '. Please login via: ' . implode(', ', $oauthType->getActiveProviders()));
        }

        // Обычная регистрация - пароль обязателен
        if (empty($input->password)) {
            throw new UnauthorizedHttpException('', AppMessages::get(AppMessages::PASSWORD_REQUIRED)->message);
        }

        if (empty($user->getPassword()) || !$passwordHasher->isPasswordValid($user, $input->password)) {
            throw new UnauthorizedHttpException('', AppMessages::get(AppMessages::INVALID_CREDENTIALS)->message);
        }

        // Создаем JWT
        $token = $jwtManager->create($user);

        // Создаем refresh token
        /** @var Cookie $refreshTokenValue */
        $refreshTokenValue = $refreshTokenService->createRefreshToken($user);

        // Создаем response
        $response = new JsonResponse(['token' => $token, 'refresh_token_expiration' => (int)$_ENV['REFRESH_TOKEN_EXPIRATION']]);
        $response->headers->setCookie($refreshTokenService->createRefreshTokenCookie($refreshTokenValue));

        return $response;
    }
}
