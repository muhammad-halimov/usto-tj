<?php

namespace App\Entity\Service;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use App\Entity\Traits\CreatedAtTrait;
use App\Entity\Traits\UpdatedAtTrait;
use App\Entity\User;
use App\Entity\User\UserServiceReview;
use App\Repository\UserServiceRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Serializer\Attribute\SerializedName;

#[ORM\Entity(repositoryClass: UserServiceRepository::class)]
#[ORM\HasLifecycleCallbacks]
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
        'masterServices:read',
    ]],
    paginationEnabled: false,
)]
class UserService
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
        'masterServices:read',
        'reviews:read',
    ])]
    private ?int $id = null;

    #[ORM\Column(length: 64, nullable: true)]
    #[Groups([
        'masterServices:read',
        'reviews:read',
    ])]
    private ?string $title = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Groups([
        'masterServices:read',
    ])]
    private ?string $description = null;

    #[ORM\Column(nullable: true)]
    #[Groups([
        'masterServices:read',
    ])]
    private ?float $price = null;

    #[ORM\ManyToOne(inversedBy: 'userServices')]
    #[Groups([
        'masterServices:read',
    ])]
    private ?User $user = null;

    #[ORM\ManyToOne(inversedBy: 'userServices')]
    #[Groups([
        'masterServices:read',
    ])]
    private ?UserServiceCategory $category = null;

    #[ORM\ManyToOne(inversedBy: 'services')]
    private ?UserServiceReview $userServiceReview = null;

    #[ORM\ManyToOne(inversedBy: 'ыservice')]
    #[Groups([
        'masterServices:read',
    ])]
    #[SerializedName('unit')]
    private ?UserServiceUnit $userServiceUnit = null;

    /**
     * @var Collection<int, UserServiceImage>
     */
    #[ORM\OneToMany(targetEntity: UserServiceImage::class, mappedBy: 'userService')]
    #[Groups([
        'masterServices:read',
    ])]
    #[SerializedName('images')]
    private Collection $userServiceImages;

    public function __construct()
    {
        $this->userServiceImages = new ArrayCollection();
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

    public function getPrice(): ?float
    {
        return $this->price;
    }

    public function setPrice(?float $price): static
    {
        $this->price = $price;

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

    public function getCategory(): ?UserServiceCategory
    {
        return $this->category;
    }

    public function setCategory(?UserServiceCategory $category): static
    {
        $this->category = $category;

        return $this;
    }

    public function getUserServiceReview(): ?UserServiceReview
    {
        return $this->userServiceReview;
    }

    public function setUserServiceReview(?UserServiceReview $userServiceReview): static
    {
        $this->userServiceReview = $userServiceReview;

        return $this;
    }

    public function getUserServiceUnit(): ?UserServiceUnit
    {
        return $this->userServiceUnit;
    }

    public function setUserServiceUnit(?UserServiceUnit $userServiceUnit): static
    {
        $this->userServiceUnit = $userServiceUnit;

        return $this;
    }

    /**
     * @return Collection<int, UserServiceImage>
     */
    public function getUserServiceImages(): Collection
    {
        return $this->userServiceImages;
    }

    public function addUserServiceImage(UserServiceImage $userServiceImage): static
    {
        if (!$this->userServiceImages->contains($userServiceImage)) {
            $this->userServiceImages->add($userServiceImage);
            $userServiceImage->setUserService($this);
        }

        return $this;
    }

    public function removeUserServiceImage(UserServiceImage $userServiceImage): static
    {
        if ($this->userServiceImages->removeElement($userServiceImage)) {
            // set the owning side to null (unless already changed)
            if ($userServiceImage->getUserService() === $this) {
                $userServiceImage->setUserService(null);
            }
        }

        return $this;
    }
}
