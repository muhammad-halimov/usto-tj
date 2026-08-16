<?php

namespace App\Entity\User;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use App\Controller\Api\CRUD\POST\User\CollectionEntry\ApiPostBlackListController;
use App\Entity\Trait\Readable\CreatedAtTrait;
use App\Entity\User;
use App\Entity\Trait\Readable\G;
use App\Entity\Trait\Readable\UpdatedAtTrait;
use App\Repository\User\BlackListRepository;
use App\State\CollectionEntry\BlackListStateProvider;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;

/**
 * Блокировка ОДНОГО конкретного пользователя в чате — единственный смысл
 * этой сущности (раньше здесь также была возможность блокировать тикет и
 * общий "тип" записи — оба варианта убраны за ненадобностью).
 *
 * Семантика блокировки — см. AccessService::checkBlackList():
 *   - Асимметрично: писать не может ТОЛЬКО заблокированная сторона (user),
 *     тот, кто поставил блок (owner), сам может продолжать переписку.
 *   - Действует только на отправку сообщений/создание чата — история
 *     переписки не удаляется и не меняется, профиль/тикеты/отзывы
 *     заблокированного остаются полностью доступны.
 */
#[ORM\Entity(repositoryClass: BlackListRepository::class)]
#[ORM\HasLifecycleCallbacks]
#[ApiResource(
    operations: [
        new GetCollection(
            uriTemplate: '/black-lists/me',
            normalizationContext: ['groups' => G::OPS_BLACK_LISTS],
            provider: BlackListStateProvider::class,
        ),
        new Post(
            uriTemplate: '/black-lists',
            controller: ApiPostBlackListController::class,
            normalizationContext: ['groups' => G::OPS_BLACK_LISTS],
        ),
        new Delete(
            uriTemplate: '/black-lists/{id}',
            requirements: ['id' => '\d+'],
            normalizationContext: ['groups' => G::OPS_BLACK_LISTS],
            security:
                "is_granted('ROLE_ADMIN')
                            or
                 object.getOwner() == user",
        ),
    ],
    paginationClientItemsPerPage: true,
    paginationEnabled: true,
    paginationItemsPerPage: 25,
    paginationMaximumItemsPerPage: 50,
)]
class BlackList
{
    use CreatedAtTrait, UpdatedAtTrait;

    public function __toString(): string
    {
        return "#{$this->id} BlackList";
    }

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups([G::BLACK_LISTS])]
    private ?int $id = null;

    /** Кто поставил блок (из Bearer-токена, не выводится в ответе). */
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    #[ApiProperty(writable: false)]
    private ?User $owner = null;

    /** Кого заблокировали. */
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'target_user_id', nullable: false, onDelete: 'CASCADE')]
    #[Groups([G::BLACK_LISTS])]
    private ?User $user = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getOwner(): ?User
    {
        return $this->owner;
    }

    public function setOwner(?User $owner): static
    {
        $this->owner = $owner;
        return $this;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): static
    {
        $this->user = $user;
        return $this;
    }
}
