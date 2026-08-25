<?php

namespace App\Repository\User;

use App\Entity\Extra\OAuthProvider;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<OAuthProvider>
 */
class UserOAuthProviderRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, OAuthProvider::class);
    }

    /**
     * Используется LinkOAuthProviderController, чтобы проверить, не занят
     * ли уже этот внешний аккаунт (кем угодно) перед привязкой — см.
     * uq_provider_id констрейнт в OAuthProvider. Отличается от
     * UserRepository::findByOAuthProvider() тем, что возвращает саму
     * связь (OAuthProvider), а не сразу User — нужно проверить владельца
     * (->getUser()) до принятия решения TAKEN/ALREADY_LINKED.
     */
    public function findOneByProviderAndId(string $provider, string $providerId): ?OAuthProvider
    {
        return $this->findOneBy(['provider' => $provider, 'providerId' => $providerId]);
    }
}
