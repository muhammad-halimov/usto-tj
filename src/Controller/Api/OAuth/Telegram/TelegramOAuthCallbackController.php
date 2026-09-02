<?php

namespace App\Controller\Api\OAuth\Telegram;

use App\Dto\OAuth\GeneralCallbackOutput;
use App\Dto\OAuth\TelegramCallbackInput;
use App\Service\Auth\RefreshTokenService;
use App\Service\OAuth\Telegram\TelegramOAuthService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\DecodingExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;

/**
 * POST /auth/telegram/callback — единственный эндпоинт Telegram-логина
 * (нет /url, нет отдельного code+state обмена — см. докблок
 * TelegramOAuthService). Не наследует Abstract*Controller (тот флоу сюда
 * не подходит структурно), но переиспользует тот же GeneralCallbackOutput
 * DTO и ту же схему access-JWT-в-теле + refresh-в-cookie, что и
 * AbstractOAuthCallbackController.
 *
 * С 27.08.2026 handleCallback() проверяет подпись (hash/authDate) —
 * см. TelegramHashVerifierService/докблок TelegramOAuthService.
 */
class TelegramOAuthCallbackController extends AbstractController
{
    public function __construct(private readonly TelegramOAuthService $telegramOAuth, readonly RefreshTokenService $refreshTokenService) {}

    /**
     * @throws TransportExceptionInterface
     * @throws ServerExceptionInterface
     * @throws RedirectionExceptionInterface
     * @throws DecodingExceptionInterface
     * @throws ClientExceptionInterface
     */
    public function __invoke(#[MapRequestPayload] TelegramCallbackInput $input): JsonResponse
    {
        // $input содержит сырые данные от Telegram Login Widget с фронта
        // (id/username/firstName/lastName/photoUrl/hash/authDate) —
        // handleCallback() проверяет hash/authDate перед тем, как доверять
        // остальным полям.
        $result = $this->telegramOAuth->handleCallback(
            $input->id,
            $input->username,
            $input->firstName,
            $input->lastName,
            $input->photoUrl,
            $input->role,
            $input->hash,
            $input->authDate,
        );

        $refreshTokenValue = $this->refreshTokenService->createRefreshToken($result['user']);

        $output = new GeneralCallbackOutput();
        $output->user = $result['user'];
        $output->token = $result['token'];
        $output->status = $result['isNew'] ? 204 : 200;

        $response = $this->json($output);
        $response->headers->setCookie($this->refreshTokenService->createRefreshTokenCookie($refreshTokenValue));

        return $response;
    }
}
