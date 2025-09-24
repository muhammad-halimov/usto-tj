<?php

namespace App\Entity\Service;

use ApiPlatform\Metadata\ApiResource;
use App\Entity\Traits\CreatedAtTrait;
use App\Entity\Traits\UpdatedAtTrait;
use App\Entity\User;
use App\Entity\UserServiceReview;
use App\Repository\UserServiceRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: UserServiceRepository::class)]
#[ORM\HasLifecycleCallbacks]
#[ApiResource]
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
    private ?int $id = null;

    #[ORM\Column(length: 64, nullable: true)]
    private ?string $title = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description = null;

    #[ORM\Column(nullable: true)]
    private ?float $price = null;

    #[ORM\ManyToOne(inversedBy: 'userServices')]
    private ?User $user = null;

    #[ORM\ManyToOne(inversedBy: 'userServices')]
    private ?UserServiceCategory $category = null;

    #[ORM\ManyToOne(inversedBy: 'services')]
    private ?UserServiceReview $userServiceReview = null;

    #[ORM\ManyToOne(inversedBy: 'ыservice')]
    private ?UserServiceUnit $userServiceUnit = null;

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
}
