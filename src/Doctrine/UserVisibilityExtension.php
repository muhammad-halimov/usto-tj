<?php

namespace App\Doctrine;

use ApiPlatform\Doctrine\Orm\Extension\QueryCollectionExtensionInterface;
use ApiPlatform\Doctrine\Orm\Util\QueryNameGeneratorInterface;
use ApiPlatform\Metadata\Operation;
use App\Entity\User;
use Doctrine\ORM\QueryBuilder;

/**
 * Doctrine ORM extension: скрывает из коллекции GET /users профили
 * пользователей, которые деактивированы или ещё не подтверждены —
 * то же условие (active = true AND approved = true), что
 * ApprovedTicketExtension применяет к публикатору тикета, и что
 * FavoriteVisibilityExtension уже применяет к вложенному user в /favorites.
 *
 * Не применяется к:
 *   GET /api/users/me            — кастомный контроллер (ApiGetMyProfileController),
 *                                   этот extension не участвует в его pipeline.
 *   GET /api/users/social-networks — кастомный контроллер (SocialNetworkController),
 *                                   не проходит через Doctrine ORM state provider вовсе.
 *   GET /api/users/{id}          — обрабатывается отдельно в
 *                                   UserGeographyLocalizationProvider::provide(),
 *                                   где то же условие продублировано для одиночного юзера.
 */
final class UserVisibilityExtension implements QueryCollectionExtensionInterface
{
    public function applyToCollection(
        QueryBuilder                $queryBuilder,
        QueryNameGeneratorInterface $queryNameGenerator,
        string                      $resourceClass,
        ?Operation                  $operation = null,
        array                       $context   = [],
    ): void {
        // Extension применяется ко всем ресурсам API Platform — фильтруем,
        // чтобы затрагивать только коллекции User, не трогая остальные.
        if ($resourceClass !== User::class) {
            return;
        }

        $alias = $queryBuilder->getRootAliases()[0];

        $queryBuilder->andWhere("$alias.active = true AND $alias.approved = true");
    }
}
