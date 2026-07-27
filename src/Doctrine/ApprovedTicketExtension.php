<?php

namespace App\Doctrine;

use ApiPlatform\Doctrine\Orm\Extension\QueryCollectionExtensionInterface;
use ApiPlatform\Doctrine\Orm\Util\QueryNameGeneratorInterface;
use ApiPlatform\Metadata\Operation;
use App\Entity\Ticket\Ticket;
use Doctrine\ORM\QueryBuilder;

final class ApprovedTicketExtension implements QueryCollectionExtensionInterface
{
    public function applyToCollection(
        QueryBuilder                $queryBuilder,
        QueryNameGeneratorInterface $queryNameGenerator,
        string                      $resourceClass,
        ?Operation                  $operation = null,
        array                       $context   = [],
    ): void {
        if ($resourceClass !== Ticket::class) {
            return;
        }

        $alias = $queryBuilder->getRootAliases()[0];
        $approvedParam = $queryNameGenerator->generateParameterName('approved');

        $queryBuilder
            ->andWhere("$alias.approved = :$approvedParam")
            ->setParameter($approvedParam, true);

        $authorAlias = $queryNameGenerator->generateJoinAlias('author');
        $masterAlias = $queryNameGenerator->generateJoinAlias('master');

        $queryBuilder
            ->leftJoin("$alias.author", $authorAlias)
            ->leftJoin("$alias.master", $masterAlias)
            ->andWhere(
                "($alias.service = true AND $masterAlias.active = true AND $masterAlias.approved = true)
                OR
                ($alias.service = false AND $authorAlias.active = true AND $authorAlias.approved = true)"
            );
    }
}
