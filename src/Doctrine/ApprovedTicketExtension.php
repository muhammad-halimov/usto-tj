<?php

namespace App\Doctrine;

use ApiPlatform\Doctrine\Orm\Extension\QueryCollectionExtensionInterface;
use ApiPlatform\Doctrine\Orm\Util\QueryNameGeneratorInterface;
use ApiPlatform\Metadata\Operation;
use App\Entity\Ticket\Ticket;
use Doctrine\ORM\QueryBuilder;

/**
 * Doctrine ORM extension: скрывает из коллекций (GET /tickets) тикеты,
 * которые не должны быть публично видны.
 *
 * Условие видимости:
 *   1. Тикет должен быть approved = true (одобрен админом).
 *   2. Публикатор тикета должен быть active = true И approved = true.
 *      Кто именно публикатор — зависит от типа тикета:
 *        - service = true  (объявление-услуга от мастера) → публикатор master
 *        - service = false (запрос от клиента)             → публикатор author
 *
 * Не применяется к:
 *   GET /api/tickets/me   — кастомный контроллер (ApiGetMyTicketsController),
 *                            репозиторий вызывается напрямую, этот extension
 *                            не участвует в его pipeline.
 *   GET /api/tickets/{id} — обрабатывается отдельно в
 *                            TicketGeographyLocalizationProvider, где похожая
 *                            логика видимости продублирована для одиночного тикета.
 */
final class ApprovedTicketExtension implements QueryCollectionExtensionInterface
{
    public function applyToCollection(
        QueryBuilder                $queryBuilder,
        QueryNameGeneratorInterface $queryNameGenerator,
        string                      $resourceClass,
        ?Operation                  $operation = null,
        array                       $context   = [],
    ): void {
        // Extension применяется ко всем ресурсам API Platform — фильтруем,
        // чтобы затрагивать только коллекции Ticket, не трогая остальные.
        if ($resourceClass !== Ticket::class) {
            return;
        }

        // Алиас корневой сущности (Ticket) в текущем QueryBuilder — обычно 't'
        // или сгенерированный API Platform алиас.
        $alias = $queryBuilder->getRootAliases()[0];

        // Именованный параметр для approved, генерируется через
        // QueryNameGeneratorInterface, чтобы избежать коллизий с параметрами
        // из других extensions/фильтров, работающих в том же QueryBuilder.
        $approvedParam = $queryNameGenerator->generateParameterName('approved');

        // Условие 1: тикет должен быть одобрен админом.
        $queryBuilder
            ->andWhere("$alias.approved = :$approvedParam")
            ->setParameter($approvedParam, true);

        // Уникальные алиасы для JOIN на author и master — генератор гарантирует,
        // что они не пересекутся с алиасами, уже используемыми другими
        // extensions/фильтрами (например ExistsFilter/SearchFilter на этих же полях).
        $authorAlias = $queryNameGenerator->generateJoinAlias('author');
        $masterAlias = $queryNameGenerator->generateJoinAlias('master');

        // LEFT JOIN (не INNER), потому что у тикета заполнено только ОДНО
        // из полей (author ИЛИ master) в зависимости от service — если бы
        // использовали INNER JOIN на оба, строка никогда бы не прошла фильтр,
        // так как одно из двух полей всегда NULL.
        $queryBuilder
            ->leftJoin("$alias.author", $authorAlias)
            ->leftJoin("$alias.master", $masterAlias)
            // Условие 2: в зависимости от типа тикета проверяем active+approved
            // именно у того юзера, который реально является публикатором:
            //   - service = true  → это объявление мастера → смотрим на master
            //   - service = false → это запрос клиента      → смотрим на author
            // Ровно одна из веток OR сработает для каждой строки, так как
            // service однозначно определяет, какое поле заполнено.
            ->andWhere(
                "($alias.service = true AND $masterAlias.active = true AND $masterAlias.approved = true)
                OR
                ($alias.service = false AND $authorAlias.active = true AND $authorAlias.approved = true)"
            );
    }
}
