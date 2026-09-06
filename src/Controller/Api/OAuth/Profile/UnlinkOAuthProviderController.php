<?php

namespace App\Controller\Api\OAuth\Profile;

use App\ApiResource\AppMessages;
use App\Entity\Extra\OAuthProvider;
use App\Entity\User;
use App\Service\Extra\UuidUtil;
use App\Exception\AppMessageException;
use App\Service\Extra\AccessService;
use DateTimeInterface;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * DELETE /profile/oauth/{provider} — отвязать провайдер от ТЕКУЩЕГО
 * (залогиненного) пользователя. Требует аутентификации — в отличие от
 * логин-флоу, ничего не создаёт и не ищет пользователей, только чистит
 * одну OAuthProvider-связь.
 *
 * Защита от блокировки собственного аккаунта: отказ (OAUTH_LAST_AUTH_
 * METHOD), если у юзера НЕТ пароля (например, завёлся только через OAuth,
 * плейсхолдер-пароль пуст) И это последний привязанный провайдер — иначе
 * пользователь потерял бы вообще все способы войти.
 */
class UnlinkOAuthProviderController extends AbstractController
{
    public function __construct(
        private readonly Security                    $security,
        private readonly AccessService               $accessService,
        private readonly EntityManagerInterface      $entityManager,
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        /** @var User $currentUser */
        $currentUser = $this->security->getUser();
        $this->accessService->check($currentUser);

        $provider = (string) $request->attributes->get('provider', '');

        /** @var OAuthProvider|null $providerEntity */
        $providerEntity = $currentUser->getOauthProviders()
            ->filter(fn(OAuthProvider $p) => $p->getProvider() === $provider)
            ->first() ?: null;

        if ($providerEntity === null) {
            throw new AppMessageException(AppMessages::OAUTH_NOT_LINKED);
        }

        $hasPassword      = !empty($currentUser->getPassword());
        $hasOtherProvider = $currentUser->getOauthProviders()->count() > 1;

        if (!$hasPassword && !$hasOtherProvider) {
            throw new AppMessageException(AppMessages::OAUTH_LAST_AUTH_METHOD);
        }

        $currentUser->removeOauthProvider($providerEntity);
        $this->entityManager->remove($providerEntity);
        $this->entityManager->flush();

        $remaining = array_map(
            fn(OAuthProvider $p) => [
                'provider' => $p->getProvider(),
                'linkedAt' => $p->getCreatedAt()->format(DateTimeInterface::ATOM),
            ],
            array_filter(
                $currentUser->getOauthProviders()->toArray(),
                fn(OAuthProvider $p) => !UuidUtil::same($p->getId(), $providerEntity->getId())
            )
        );

        return $this->json(['providers' => array_values($remaining)]);
    }
}
