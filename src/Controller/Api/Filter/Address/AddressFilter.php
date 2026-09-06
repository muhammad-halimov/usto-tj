<?php

namespace App\Controller\Api\Filter\Address;

use ApiPlatform\Doctrine\Orm\Filter\AbstractFilter;
use ApiPlatform\Doctrine\Orm\Util\QueryNameGeneratorInterface;
use ApiPlatform\Metadata\Operation;
use Doctrine\ORM\QueryBuilder;

final class AddressFilter extends AbstractFilter
{
    protected function filterProperty(
        string                      $property, $value,
        QueryBuilder                $queryBuilder,
        QueryNameGeneratorInterface $queryNameGenerator,
        string                      $resourceClass,
        ?Operation                  $operation = null,
        array                       $context = []
    ): void {
        $geographyFields = [
            'province',
            'district',
            'city',
            'settlement',
            'community',
            'village',
            'suburb'
        ];

        if (!in_array($property, $geographyFields)) return;

        $alias = $queryBuilder->getRootAliases()[0];
        $addressAlias = $queryNameGenerator->generateJoinAlias('addresses');
        $geoAlias = $queryNameGenerator->generateJoinAlias($property);
        $parameterName = $queryNameGenerator->generateParameterName($property);

        // Получаем существующие JOIN чтобы не дублировать
        $existingJoins = $queryBuilder->getDQLPart('join');
        $addressJoinExists = false;

        foreach ($existingJoins as $joins) {
            foreach ($joins as $join) {
                if (str_contains($join->getJoin(), '.addresses')) {
                    $addressJoinExists = true;
                    $addressAlias = $join->getAlias();
                    break 2;
                }
            }
        }

        // JOIN с адресами если еще не было
        if (!$addressJoinExists) {
            $queryBuilder->leftJoin("$alias.addresses", $addressAlias);
        }

        // JOIN с географическим объектом
        $queryBuilder->leftJoin("$addressAlias.$property", $geoAlias);

        // Проверяем, является ли значение ID (UUID) или строкой (title/description).
        //
        // БАГФИКС (06.09.2026, переход на UUID-PK): было is_numeric($value)
        // — работало, пока ID были числами. У UUID ("01a0781d-0592-...")
        // is_numeric() всегда false (там дефисы и буквы) — фильтр по ID
        // тихо переставал бы работать вообще, ВСЕГДА уходя в ветку
        // текстового поиска по title/description (тот же класс бага, что
        // уже чинили в ApiGetMyChatsController — is_numeric() на реальном
        // UUID всегда false).
        if (preg_match('/^[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{12}$/', (string) $value)) {
            // Поиск по ID
            $queryBuilder
                ->andWhere("$geoAlias.id = :$parameterName")
                ->setParameter($parameterName, (string) $value);
        } else {
            // JOIN с переводами для поиска на всех языках (ru, tj, eng)
            $translationAlias = $queryNameGenerator->generateJoinAlias('translations');
            $queryBuilder->leftJoin("$geoAlias.translations", $translationAlias);

            // Поиск по title/description основного поля и по всем переводам
            $queryBuilder
                ->andWhere(
                    "LOWER($geoAlias.title) LIKE LOWER(:$parameterName) OR " .
                    "LOWER($geoAlias.description) LIKE LOWER(:$parameterName) OR " .
                    "LOWER($translationAlias.title) LIKE LOWER(:$parameterName)"
                )
                ->setParameter($parameterName, "%$value%");
        }
    }

    public function getDescription(string $resourceClass): array
    {
        $description = [];
        $geographyFields = [
            'province' => 'Province',
            'district' => 'District',
            'city' => 'City',
            'settlement' => 'Settlement',
            'community' => 'Community',
            'village' => 'Village',
            'suburb' => 'Suburb',
        ];

        foreach ($geographyFields as $field => $label) {
            $description[$field] = [
                'property' => $field,
                'type' => 'string',
                'required' => false,
                'description' => "Filter by $label (UUID for exact ID match, text for case-insensitive partial search in title/description)",
            ];
        }

        return $description;
    }
}
