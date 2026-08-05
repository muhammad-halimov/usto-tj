<?php

namespace App\Doctrine;

use ApiPlatform\Doctrine\Orm\Extension\QueryCollectionExtensionInterface;
use ApiPlatform\Doctrine\Orm\Util\QueryNameGeneratorInterface;
use ApiPlatform\Metadata\Operation;
use App\Entity\User\Favorite;
use Doctrine\ORM\QueryBuilder;

/**
 * Doctrine ORM extension: скрывает из /api/favorites* записи, чья цель
 * (тикет или пользователь) сейчас не должна быть публично видна — то же
 * условие видимости, что ApprovedTicketExtension применяет к /api/tickets.
 *
 * Раньше это условие проверялось постфактум в FavoriteStateProvider —
 * уже ПОСЛЕ того, как Doctrine применял LIMIT/OFFSET для пагинации.
 * Из-за этого конкретная страница могла прийти пустой, даже если у
 * пользователя есть видимые записи на других страницах: пагинация и
 * фильтрация видимости работали независимо друг от друга.
 *
 * Перенос условия в QueryBuilder чинит это: невидимые записи исключаются
 * ДО применения LIMIT/OFFSET, поэтому пагинация (и totalItems) снова
 * соответствуют реально видимому набору записей.
 */
final class FavoriteVisibilityExtension implements QueryCollectionExtensionInterface
{
    public function applyToCollection(
        QueryBuilder                $queryBuilder,
        QueryNameGeneratorInterface $queryNameGenerator,
        string                      $resourceClass,
        ?Operation                  $operation = null,
        array                       $context   = [],
    ): void {
        // Extension применяется ко всем ресурсам API Platform — фильтруем,
        // чтобы затрагивать только коллекции Favorite, не трогая остальные.
        if ($resourceClass !== Favorite::class) {
            return;
        }

        $alias = $queryBuilder->getRootAliases()[0];

        // Уникальные алиасы для JOIN на target ticket/user — генератор
        // гарантирует, что они не пересекутся с алиасами других
        // extensions/фильтров (например CurrentUserCollectionExtension).
        $ticketAlias = $queryNameGenerator->generateJoinAlias('ticket');
        $userAlias   = $queryNameGenerator->generateJoinAlias('user');

        // LEFT JOIN, потому что у записи заполнено только ОДНО из полей
        // (user ИЛИ ticket) в зависимости от типа избранного.
        $queryBuilder
            ->leftJoin("$alias.ticket", $ticketAlias)
            ->leftJoin("$alias.user", $userAlias)
            ->andWhere(
                "($alias.ticket IS NULL OR $ticketAlias.approved = true)
                AND
                ($alias.user IS NULL OR ($userAlias.active = true AND $userAlias.approved = true))"
            );
    }
}
