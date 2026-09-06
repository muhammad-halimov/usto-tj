<?php

namespace App\Entity\TechSupport;

use App\Service\Extra\UuidUtil;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use App\Controller\Api\CRUD\DELETE\TechSupport\Message\ApiDeleteTechSupportMessageController;
use App\Controller\Api\CRUD\GET\TechSupport\Message\ApiGetTechSupportMessageController;
use App\Controller\Api\CRUD\PATCH\TechSupport\Message\ApiPatchTechSupportMessageController;
use App\Controller\Api\CRUD\POST\Image\Image\ApiPostUniversalImageController;
use App\Controller\Api\CRUD\POST\TechSupport\Message\ApiPostTechSupportMessageController;
use App\Dto\Image\ImageInput;
use App\Entity\Contract\EditableMessageInterface;
use App\Entity\Extra\MultipleImage;
use App\Entity\Trait\Readable\CreatedAtTrait;
use App\Entity\Trait\Readable\DescriptionTrait;
use App\Entity\Trait\Readable\G;
use App\Entity\Trait\Readable\UpdatedAtTrait;
use App\Entity\User;
use App\Repository\TechSupport\TechSupportMessageRepository;
use DateTimeImmutable;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\IdGenerator\UuidGenerator;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Serializer\Attribute\Groups;

#[ORM\Entity(repositoryClass: TechSupportMessageRepository::class)]
#[ORM\HasLifecycleCallbacks]
#[ApiResource(
    operations: [
        new Get(
            uriTemplate: '/tech-support-messages/{id}',
            requirements: ['id' => '[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{12}'],
            controller: ApiGetTechSupportMessageController::class,
        ),
        new Patch(
           uriTemplate: '/tech-support-messages/{id}',
           requirements: ['id' => '[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{12}'],
           controller: ApiPatchTechSupportMessageController::class,
        ),
        new Delete(
            uriTemplate: '/tech-support-messages/{id}',
            requirements: ['id' => '[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{12}'],
            controller: ApiDeleteTechSupportMessageController::class,
        ),
        new Post(
            uriTemplate: '/tech-support-messages',
            controller: ApiPostTechSupportMessageController::class,
        ),
        new Post(
            uriTemplate: '/tech-support-messages/{id}/upload-images',
            inputFormats: ['multipart' => ['multipart/form-data']],
            requirements: ['id' => '[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{12}'],
            controller: ApiPostUniversalImageController::class,
            input: ImageInput::class,
        ),
    ],
    normalizationContext: ['groups' => G::OPS_TECH_MSGS],
    paginationClientItemsPerPage: true,
    paginationEnabled: true,
    paginationItemsPerPage: 25,
    paginationMaximumItemsPerPage: 50,
)]
class TechSupportMessage implements EditableMessageInterface
{
    use CreatedAtTrait, UpdatedAtTrait, DescriptionTrait;

    public function __toString(): string
    {
        return '#' . UuidUtil::short($this->id) . ($this->description ? " $this->description" : '');
    }

    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: UuidGenerator::class)]
    #[ORM\Column(type: 'uuid', unique: true)]
    #[Groups([
        G::TECH_SUPPORT_MESSAGES,
        G::TECH_SUPPORT,
    ])]
    private ?Uuid $id = null;

    #[ORM\ManyToOne(inversedBy: 'techSupportMessages')]
    #[ORM\JoinColumn(name: 'author_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    #[Groups([
        G::TECH_SUPPORT_MESSAGES,
        G::TECH_SUPPORT,
    ])]
    #[ApiProperty(writable: false)]
    private ?User $author = null;

    #[ORM\ManyToOne(inversedBy: 'techSupportMessages')]
    #[ORM\JoinColumn(name: 'tech_support_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    #[Groups([
        G::TECH_SUPPORT_MESSAGES,
    ])]
    private ?TechSupport $techSupport = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    #[Groups([
        G::TECH_SUPPORT_MESSAGES,
        G::TECH_SUPPORT,
    ])]
    #[ApiProperty(writable: false)]
    private ?DateTimeImmutable $readAt = null;

    /**
     * "Помечать сообщение как изменено" — выставляется автоматически при
     * первой правке текста (см. ApiPatchTechSupportMessageController) и
     * остаётся true навсегда, даже если текст правили несколько раз.
     * Сама история правок — в EntityRevision (audit trail), это поле —
     * только видимый пользователю/оператору признак "уже редактировалось".
     */
    #[ORM\Column(options: ['default' => false])]
    #[Groups([
        G::TECH_SUPPORT_MESSAGES,
        G::TECH_SUPPORT,
    ])]
    #[ApiProperty(writable: false)]
    private bool $edited = false;

    /**
     * Мягкое удаление автором (см. DELETED_PLACEHOLDER,
     * ApiDeleteTechSupportMessageController) — отдельно от $edited: это не
     * "правка", а замена содержимого на плейсхолдер, физическая строка в
     * БД остаётся (audit trail).
     */
    #[ORM\Column(options: ['default' => false])]
    #[Groups([
        G::TECH_SUPPORT_MESSAGES,
        G::TECH_SUPPORT,
    ])]
    #[ApiProperty(writable: false)]
    private bool $deletedByAuthor = false;

    /**
     * @var Collection<int, MultipleImage>
     */
    #[ORM\OneToMany(targetEntity: MultipleImage::class, mappedBy: 'techSupportMessage', cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['priority' => 'ASC'])]
    #[ApiProperty(writable: false)]
    #[Groups([
        G::TECH_SUPPORT_MESSAGES,
        G::TECH_SUPPORT,
    ])]
    private Collection $images;

    public function __construct()
    {
        $this->images = new ArrayCollection();
    }

    public function getId(): ?Uuid
    {
        return $this->id;
    }

    public function getAuthor(): ?User
    {
        return $this->author;
    }

    public function setAuthor(?User $author): static
    {
        $this->author = $author;

        return $this;
    }

    public function getTechSupport(): ?TechSupport
    {
        return $this->techSupport;
    }

    public function setTechSupport(?TechSupport $techSupport): static
    {
        $this->techSupport = $techSupport;

        return $this;
    }

    /**
     * @return Collection<int, MultipleImage>
     */
    public function getImages(): Collection
    {
        return $this->images;
    }

    public function addImage(MultipleImage $image): static
    {
        if (!$this->images->contains($image)) {
            $this->images->add($image);
            $image->setTechSupportMessage($this);
        }

        return $this;
    }

    public function removeImage(MultipleImage $image): static
    {
        if ($this->images->removeElement($image)) {
            // set the owning side to null (unless already changed)
            if ($image->getTechSupportMessage() === $this) {
                $image->setTechSupportMessage(null);
            }
        }

        return $this;
    }

    public function getReadAt(): ?DateTimeImmutable
    {
        return $this->readAt;
    }

    public function setReadAt(?DateTimeImmutable $readAt): static
    {
        $this->readAt = $readAt;

        return $this;
    }

    public function isEdited(): bool
    {
        return $this->edited;
    }

    public function setEdited(bool $edited): static
    {
        $this->edited = $edited;

        return $this;
    }

    public function isDeletedByAuthor(): bool
    {
        return $this->deletedByAuthor;
    }

    public function setDeletedByAuthor(bool $deletedByAuthor): static
    {
        $this->deletedByAuthor = $deletedByAuthor;

        return $this;
    }
}
