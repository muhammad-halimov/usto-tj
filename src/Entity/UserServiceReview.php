<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use App\Entity\Service\UserService;
use App\Entity\Traits\CreatedAtTrait;
use App\Entity\Traits\UpdatedAtTrait;
use App\Repository\UserServiceReviewRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: UserServiceReviewRepository::class)]
#[ORM\HasLifecycleCallbacks]
#[ApiResource]
class UserServiceReview
{
    use UpdatedAtTrait, CreatedAtTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'userServiceReviews')]
    private ?User $user = null;

    #[ORM\ManyToOne(inversedBy: 'userServiceReviews')]
    private ?User $reviewer = null;

    #[ORM\Column(nullable: true)]
    private ?float $rating = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description = null;

    /**
     * @var Collection<int, UserService>
     */
    #[ORM\OneToMany(targetEntity: UserService::class, mappedBy: 'userServiceReview')]
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
