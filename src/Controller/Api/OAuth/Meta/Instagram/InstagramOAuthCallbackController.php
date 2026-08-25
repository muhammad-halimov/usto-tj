<?php

namespace App\Controller\Api\OAuth\Meta\Instagram;

use App\Controller\Api\OAuth\AbstractOAuthCallbackController;
use App\Service\Auth\RefreshTokenService;
use App\Service\OAuth\Meta\Instagram\InstagramOAuthService;

/**
 * POST /auth/instagram/callback — просто фиксирует InstagramOAuthService,
 * вся логика в AbstractOAuthCallbackController. Если сюда прилетает
 * generic 400 "Мубодилаи код бо провайдер ноком шуд" — реальная причина
 * скрыта внутри InstagramOAuthService::exchangeCodeForTokens(), см. её
 * докблок для деталей/диагностики.
 */
class InstagramOAuthCallbackController extends AbstractOAuthCallbackController
{
    public function __construct(InstagramOAuthService $oauthService, RefreshTokenService $refreshTokenService)
    {
        parent::__construct($oauthService, $refreshTokenService);
    }
}
