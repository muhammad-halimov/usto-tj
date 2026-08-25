<?php

namespace App\Controller\Api\OAuth;

use App\Dto\OAuth\GeneralCallbackInput;
use App\Dto\OAuth\GeneralCallbackOutput;
use App\Service\Auth\RefreshTokenService;
use App\Service\OAuth\Interface\OAuthServiceInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;

/**
 * POST /auth/{provider}/callback — финальный шаг code+state flow (см.
 * код-флоу в GeneralOAuth). Наследники (Google/Facebook/
 * Instagram*OAuthCallbackController) не добавляют ничего своего — только
 * фиксируют конкретный *OAuthService, вся логика — здесь и в
 * AbstractOAuthService::handleCode().
 *
 * Неаутентифицированный роут (это и есть логин/регистрация) — в отличие от
 * LinkOAuthProviderController (там ровно та же схема code+state, но уже
 * ДЛЯ залогиненного пользователя, привязывающего доп. провайдера).
 */
abstract class AbstractOAuthCallbackController extends AbstractController
{
    public function __construct(protected readonly OAuthServiceInterface $oauthService, protected readonly RefreshTokenService $refreshTokenService) {}

    public function __invoke(#[MapRequestPayload] GeneralCallbackInput $input): JsonResponse
    {
        // handleCode() делает всё: сверяет state, обменивает code,
        // тянет профиль, находит/создаёт User, выдаёт JWT — см. её докблок.
        $result = $this->oauthService->handleCode(
            $input->getCode(),
            $input->getState(),
            $input->role
        );

        $output = new GeneralCallbackOutput();
        $output->user = $result['user'];
        $output->token = $result['token'];
        // ВАЖНО: это ПОЛЕ в теле ответа, а не реальный HTTP-статус — сам
        // JSON-ответ ниже ($this->json($output)) физически всегда 200,
        // никакого response->setStatusCode() тут нет. Фронтенд должен
        // смотреть именно на это поле, чтобы отличить "залогинили
        // существующего" (200) от "завели нового" (204) — коды выбраны по
        // REST-соглашению (204 у POST обычно означает "создано без тела",
        // тут смысл переиспользован чисто как маркер "это новый юзер"),
        // но реального 204-ответа сервер не отдаёт.
        $output->status = $result['isNew'] ? 204 : 200;

        // Тот же механизм, что и у обычного логина по паролю — access JWT
        // в теле, refresh — в HttpOnly-cookie (см. RefreshTokenService).
        $refreshTokenValue = $this->refreshTokenService->createRefreshToken($result['user']);
        $response = $this->json($output);
        $response->headers->setCookie($this->refreshTokenService->createRefreshTokenCookie($refreshTokenValue));

        return $response;
    }
}
