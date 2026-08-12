<?php

namespace App\Controller\Api\CRUD\POST\User\CollectionEntry;

use App\Entity\Extra\AbstractCollectionEntry;
use App\Entity\Ticket\Ticket;
use App\Entity\Trait\Readable\G;
use App\Entity\User;
use App\Entity\User\Favorite;
use App\Repository\User\FavoriteRepository;

/**
 * Блокировка в чёрном списке больше не мешает добавлению в избранное —
 * блокировка теперь чисто про запрет писать в чате (см.
 * AccessService::checkBlackList), не про ограничение остального
 * взаимодействия. Раньше здесь были validateUser/validateTicket с проверкой
 * checkBlackList — убраны намеренно.
 */
class ApiPostFavoriteController extends AbstractPostCollectionEntryController
{
    public function __construct(private readonly FavoriteRepository $repository) {}

    protected function setSerializationGroups(): array { return G::OPS_FAVORITES; }

    protected function newEntry(): AbstractCollectionEntry { return new Favorite(); }

    protected function findDuplicate(User $owner, ?User $user = null, ?Ticket $ticket = null): ?Favorite
    {
        return $this->repository->findDuplicate($owner, $user, $ticket);
    }
}
