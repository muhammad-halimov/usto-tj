<?php

namespace App\Controller\Api\OAuth\Meta\Facebook;

use App\Controller\Api\OAuth\AbstractOAuthCallbackController;
use App\Service\Auth\RefreshTokenService;
use App\Service\OAuth\Meta\Facebook\FacebookOAuthService;

/** POST /auth/facebook/callback — просто фиксирует FacebookOAuthService, вся логика в AbstractOAuthCallbackController. */
class FacebookOAuthCallbackController extends AbstractOAuthCallbackController
{
    public function __construct(FacebookOAuthService $oauthService, RefreshTokenService $refreshTokenService)
    {
        parent::__construct($oauthService, $refreshTokenService);
    }
}
