<?php

namespace App\Entity\User;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use App\Entity\Service\UserService;
use App\Entity\Traits\CreatedAtTrait;
use App\Entity\Traits\UpdatedAtTrait;
use App\Entity\User;
use App\Repository\UserServiceReviewRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;

#[ORM\Entity(repositoryClass: UserServiceReviewRepository::class)]
#[ORM\HasLifecycleCallbacks]
#[ApiResource(
    operations: [
        new Get(),
        new GetCollection(),
        new Post(),
        new Patch(security:
            "is_granted('ROLE_ADMIN') or
             is_granted('ROLE_MASTER') or
             is_granted('ROLE_CLIENT') or"
        ),
        new Delete(security:
            "is_granted('ROLE_ADMIN') or
             is_granted('ROLE_MASTER') or
             is_granted('ROLE_CLIENT') or"
        )
    ],
    normalizationContext: ['groups' => [
        'reviews:read',
    ]],
    paginationEnabled: false,
)]class UserServiceReview
{
    use UpdatedAtTrait, CreatedAtTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups([
        'reviews:read',
    ])]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'userServiceReviews')]
    #[Groups([
        'reviews:read',
    ])]
    private ?User $user = null;

    #[ORM\ManyToOne(inversedBy: 'userServiceReviews')]
    #[Groups([
        'reviews:read',
    ])]
    private ?User $reviewer = null;

    #[ORM\Column(nullable: true)]
    #[Groups([
        'reviews:read',
    ])]
    private ?float $rating = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Groups([
        'reviews:read',
    ])]
    private ?string $description = null;

    /**
     * @var Collection<int, UserService>
     */
    #[ORM\OneToMany(targetEntity: UserService::class, mappedBy: 'userServiceReview')]
    #[Groups([
        'reviews:read',
    ])]
    private Collection $services;

    public function __construct()
    {
        $this->services = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
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

    public function getReviewer(): ?User
    {
        return $this->reviewer;
    }

    public function setReviewer(?User $reviewer): static
    {
        $this->reviewer = $reviewer;

        return $this;
    }

    public function getRating(): ?float
    {
        return $this->rating;
    }

    public function setRating(?float $rating): static
    {
        $this->rating = $rating;

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

    /**
     * @return Collection<int, UserService>
     */
    public function getServices(): Collection
    {
        return $this->services;
    }

    public function addService(UserService $service): static
    {
        if (!$this->services->contains($service)) {
            $this->services->add($service);
            $service->setUserServiceReview($this);
        }

        return $this;
    }

    public function removeService(UserService $service): static
    {
        if ($this->services->removeElement($service)) {
            if ($service->getUserServiceReview() === $this) {
                $service->setUserServiceReview(null);
            }
        }

        return $this;
    }
}
