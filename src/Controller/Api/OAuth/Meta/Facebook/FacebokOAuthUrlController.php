<?php

namespace App\Controller\Api\OAuth\Meta\Facebook;

use App\Controller\Api\OAuth\AbstractOAuthUrlController;
use App\Service\OAuth\Meta\Facebook\FacebookOAuthService;

/**
 * GET /auth/facebook/url — просто фиксирует FacebookOAuthService, вся
 * логика в AbstractOAuthUrlController.
 *
 * NB: имя файла/класса — "Facebok" (без второй "o") — опечатка, но менять
 * не стал: класс завязан на неё через DI/маршрутизацию, переименование
 * вне рамок этой задачи (только комментарии).
 */
class FacebokOAuthUrlController extends AbstractOAuthUrlController
{
    public function __construct(FacebookOAuthService $oauthService)
    {
        parent::__construct($oauthService);
    }
}
