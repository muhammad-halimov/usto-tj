<?php

namespace App\Controller\Api\Filter\Extra;

use ApiPlatform\Doctrine\Orm\Filter\AbstractFilter;
use ApiPlatform\Doctrine\Orm\Util\QueryNameGeneratorInterface;
use ApiPlatform\Metadata\Operation;
use Doctrine\ORM\QueryBuilder;

/**
 * Filters Favorite entries by type=user|ticket.
 * Used instead of the built-in SearchFilter because Favorite extends a MappedSuperclass.
 * BlackList no longer uses this filter — a block is always exactly one user, no type to filter by.
 */
final class CollectionEntryTypeFilter extends AbstractFilter
{
    protected function filterProperty(
        string                      $property,
                                    $value,
        QueryBuilder                $queryBuilder,
        QueryNameGeneratorInterface $queryNameGenerator,
        string                      $resourceClass,
        ?Operation                  $operation = null,
        array                       $context   = [],
    ): void {
        $alias = $queryBuilder->getRootAliases()[0];

        if ($property === 'type') {
            if (!in_array($value, ['user', 'ticket'], true)) return;

            $param = $queryNameGenerator->generateParameterName('type');
            $queryBuilder
                ->andWhere("$alias.type = :$param")
                ->setParameter($param, $value);

            return;
        }

        // БАГФИКС (06.09.2026, переход на UUID-PK): было is_numeric($value)
        // — на реальном UUID всегда false (дефисы/буквы), фильтр молча
        // переставал бы срабатывать вообще — НЕ "пусто", а хуже: просто
        // игнорировался бы, отдавая ВСЕ записи без фильтрации по
        // user/ticket. is_string() — минимальная защита от мусора
        // (массив/объект в query), не строгая валидация формата UUID:
        // не найдёт — просто 0 совпадений, не критично, в отличие от
        // молчаливого пропуска всего фильтра.
        if ($property === 'user' && is_string($value) && $value !== '') {
            $param = $queryNameGenerator->generateParameterName('user');
            $queryBuilder
                ->andWhere("IDENTITY($alias.user) = :$param")
                ->setParameter($param, $value);

            return;
        }

        if ($property === 'ticket' && is_string($value) && $value !== '') {
            $param = $queryNameGenerator->generateParameterName('ticket');
            $queryBuilder
                ->andWhere("IDENTITY($alias.ticket) = :$param")
                ->setParameter($param, $value);
        }
    }

    public function getDescription(string $resourceClass): array
    {
        return [
            'type' => [
                'property' => 'type',
                'type'     => 'string',
                'required' => false,
                'openapi'  => ['description' => 'Filter by entry type: user or ticket'],
            ],
            'user' => [
                'property' => 'user',
                'type'     => 'string',
                'required' => false,
                'openapi'  => ['description' => 'Filter by target user ID (UUID)'],
            ],
            'ticket' => [
                'property' => 'ticket',
                'type'     => 'string',
                'required' => false,
                'openapi'  => ['description' => 'Filter by target ticket ID (UUID)'],
            ],
        ];
    }
}
