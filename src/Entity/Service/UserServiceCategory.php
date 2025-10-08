<?php

namespace App\Entity\Service;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use App\Entity\Ticket\UserTicket;
use App\Entity\Traits\CreatedAtTrait;
use App\Entity\Traits\UpdatedAtTrait;
use App\Repository\ServiceCategoryRepository;
use DateTime;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;
use Vich\UploaderBundle\Mapping\Annotation as Vich;

#[ORM\Entity(repositoryClass: ServiceCategoryRepository::class)]
#[ORM\HasLifecycleCallbacks]
#[Vich\Uploadable]
#[ApiResource(
    operations: [
        new Get(),
        new GetCollection(),
        new Post(),
        new Patch(security:
            "is_granted('ROLE_ADMIN') or
             is_granted('ROLE_MASTER')"
        ),
        new Delete(security:
            "is_granted('ROLE_ADMIN') or
             is_granted('ROLE_MASTER')"
        )
    ],
    normalizationContext: ['groups' => [
        'masterServiceCategories:read',
    ]],
    paginationEnabled: false,
)]class UserServiceCategory
{
    use UpdatedAtTrait, CreatedAtTrait;

    public function __toString(): string
    {
        return $this->title;
    }

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups([
        'masterServiceCategories:read',
        'masterServices:read',
    ])]
    private ?int $id = null;

    #[ORM\Column(length: 64, nullable: true)]
    #[Groups([
        'masterServiceCategories:read',
        'masterServices:read',
    ])]
    private ?string $title = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Groups([
        'masterServiceCategories:read',
    ])]
    private ?string $description = null;

    #[Vich\UploadableField(mapping: 'service_category_photos', fileNameProperty: 'image')]
    #[Assert\Image(mimeTypes: ['image/png', 'image/jpeg', 'image/jpg', 'image/webp'])]
    #[Groups([
        'masterServiceCategories:read',
        'masterServices:read',
    ])]
    private ?File $imageFile = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $image = null;

    /**
     * @var Collection<int, UserService>
     */
    #[ORM\OneToMany(targetEntity: UserService::class, mappedBy: 'category')]
    private Collection $userServices;

    /**
     * @var Collection<int, UserTicket>
     */
    #[ORM\OneToMany(targetEntity: UserTicket::class, mappedBy: 'category')]
    private Collection $userTickets;

    public function __construct()
    {
        $this->userServices = new ArrayCollection();
        $this->userTickets = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setTitle(?string $title): static
    {
        $this->title = $title;

        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): static
    {
        $this->description = $description;

        return $this;
    }

    public function getImage(): ?string
    {
        return $this->image;
    }

    public function setImage(?string $image): static
    {
        $this->image = $image;

        return $this;
    }

    public function getImageFile(): ?File
    {
        return $this->imageFile;
    }

    public function setImageFile(?File $imageFile): self
    {
        $this->imageFile = $imageFile;
        if (null !== $imageFile) {
            $this->updatedAt = new DateTime();
        }

        return $this;
    }

    /**
     * @return Collection<int, UserService>
     */
    public function getUserServices(): Collection
    {
        return $this->userServices;
    }

    public function addUserService(UserService $userService): static
    {
        if (!$this->userServices->contains($userService)) {
            $this->userServices->add($userService);
            $userService->setCategory($this);
        }

        return $this;
    }

    public function removeUserService(UserService $userService): static
    {
        if ($this->userServices->removeElement($userService)) {
            // set the owning side to null (unless already changed)
            if ($userService->getCategory() === $this) {
                $userService->setCategory(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, UserTicket>
     */
    public function getUserTickets(): Collection
    {
        return $this->userTickets;
    }

    public function addUserTicket(UserTicket $userTicket): static
    {
        if (!$this->userTickets->contains($userTicket)) {
            $this->userTickets->add($userTicket);
            $userTicket->setCategory($this);
        }

        return $this;
    }

    public function removeUserTicket(UserTicket $userTicket): static
    {
        if ($this->userTickets->removeElement($userTicket)) {
            // set the owning side to null (unless already changed)
            if ($userTicket->getCategory() === $this) {
                $userTicket->setCategory(null);
            }
        }

        return $this;
    }
}
