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
 * условие видимости, что ApprovedTicketExtension применяет к /api/tickets
 * и UserVisibilityExtension — к /api/users.
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
 *
 * ВАЖНО: для target-тикета изначально здесь проверялся только
 * ticket.approved — тот же баг, что был в TicketGeographyLocalizationProvider
 * (см. её докблок): деактивированный/неподтверждённый ПУБЛИКАТОР тикета
 * не проверялся вовсе. Из-за этого уже одобренный тикет, чей автор/мастер
 * потом деактивировался, продолжал полностью (со всеми вложенными данными
 * мастера) отдаваться через GET /favorites/me, хотя сам он уже был скрыт
 * и из GET /tickets, и из GET /tickets/{id}. Условие ниже теперь дословно
 * повторяет ApprovedTicketExtension.
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

        // Публикатор target-тикета (author или master, в зависимости от
        // service) — те же два JOIN'а, что использует ApprovedTicketExtension,
        // но повешенные на уже присоединённый ticketAlias, а не на корневой
        // алиас Ticket-запроса.
        $ticketAuthorAlias = $queryNameGenerator->generateJoinAlias('ticketAuthor');
        $ticketMasterAlias = $queryNameGenerator->generateJoinAlias('ticketMaster');

        // LEFT JOIN везде, потому что у записи заполнено только ОДНО из полей
        // (user ИЛИ ticket) в зависимости от типа избранного, а у самого
        // тикета — только ОДНО из author/master в зависимости от service.
        $queryBuilder
            ->leftJoin("$alias.ticket", $ticketAlias)
            ->leftJoin("$alias.user", $userAlias)
            ->leftJoin("$ticketAlias.author", $ticketAuthorAlias)
            ->leftJoin("$ticketAlias.master", $ticketMasterAlias)
            ->andWhere(
                "($alias.ticket IS NULL OR (
                    $ticketAlias.approved = true
                    AND (
                        ($ticketAlias.service = true AND $ticketMasterAlias.active = true AND $ticketMasterAlias.approved = true)
                        OR
                        ($ticketAlias.service = false AND $ticketAuthorAlias.active = true AND $ticketAuthorAlias.approved = true)
                    )
                ))
                AND
                ($alias.user IS NULL OR ($userAlias.active = true AND $userAlias.approved = true))"
            );
    }
}
