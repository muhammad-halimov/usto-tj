<?php

namespace App\Security;

use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Security\Http\Authorization\AccessDeniedHandlerInterface;

readonly class AdminAccessDeniedHandler implements AccessDeniedHandlerInterface // Редирект для не админов Security.yaml: access_denied_handler: App\Security\AdminAccessDeniedHandler
{
    public function __construct(private UrlGeneratorInterface $urlGenerator) {}

    public function handle(Request $request, AccessDeniedException $accessDeniedException): ?Response
    {
        return new RedirectResponse($this->urlGenerator->generate('app_login'));
    }
}
