<?php

namespace App\Controller\Api\CRUD\POST\User\CollectionEntry;

use App\ApiResource\AppMessages;
use App\Controller\Api\CRUD\Abstract\AbstractApiPostController;
use App\Dto\User\BlackListInput;
use App\Entity\Trait\Readable\G;
use App\Entity\User;
use App\Entity\User\BlackList;
use App\Repository\User\BlackListRepository;
use App\Service\Extra\LocalizationService;

/**
 * Блокировка пользователя в чате. Больше не наследует
 * AbstractPostCollectionEntryController — та шаблонная логика "user ИЛИ
 * ticket" была нужна только пока BlackList поддерживал оба варианта, как
 * Favorite. Теперь блокировка — это всегда ровно один пользователь,
 * отдельный простой контроллер понятнее общей ветвящейся логики.
 */
class ApiPostBlackListController extends AbstractApiPostController
{
    public function __construct(
        private readonly BlackListRepository $repository,
        private readonly LocalizationService  $localizationService,
    ) {}

    protected function getInputClass(): string { return BlackListInput::class; }

    protected function setSerializationGroups(): array { return G::OPS_BLACK_LISTS; }

    protected function getUserGrade(): string { return 'triple'; }

    protected function afterFetch(object|array $entity, ?User $user): void
    {
        /** @var BlackList $entity */
        if ($entity->getOwner()) $this->localizationService->localizeUser($entity->getOwner(), $this->getLocale());
        if ($entity->getUser())  $this->localizationService->localizeUser($entity->getUser(), $this->getLocale());
    }

    protected function handle(?User $bearer, object $dto): object
    {
        /** @var BlackListInput $dto */
        if (!$dto->user) return $this->errorJson(AppMessages::USER_NOT_FOUND);

        if ($this->repository->findDuplicate($bearer, $dto->user))
            return $this->errorJson(AppMessages::ALREADY_ADDED);

        return (new BlackList())->setOwner($bearer)->setUser($dto->user);
    }
}
