<?php

namespace App\Controller\Api\OAuth;

use App\Dto\OAuth\GeneralAuthUrlOutput;
use App\Service\OAuth\Interface\OAuthServiceInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;

/**
 * GET /auth/{provider}/url — первый шаг code+state flow (см. код-флоу в
 * GeneralOAuth). Наследники (Google/Facebook/Instagram*OAuthUrlController)
 * не добавляют ничего своего — просто фиксируют конкретный
 * *OAuthService через конструктор, чтобы ApiResource мог указать разные
 * контроллеры на разные provider-роуты, реализация одна и та же.
 *
 * Неаутентифицированный роут — вызывается ДО логина (это его и есть
 * начало), $output — DTO, не сущность, ApiResource биндит его через
 * output: GeneralAuthUrlOutput::class на стороне ApiResource-операции.
 */
abstract class AbstractOAuthUrlController extends AbstractController
{
    public function __construct(protected readonly OAuthServiceInterface $oauthService) {}

    public function __invoke(GeneralAuthUrlOutput $output): JsonResponse
    {
        // generateOAuthRedirectUri() уже целиком построил URL, включая
        // anti-CSRF state, — контроллеру остаётся только вернуть его.
        $output->url = $this->oauthService->generateOAuthRedirectUri();
        return $this->json($output);
    }
}
