<?php

namespace App\Entity\User;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use App\Entity\Ticket\Ticket;
use App\Entity\Traits\CreatedAtTrait;
use App\Entity\Traits\UpdatedAtTrait;
use App\Entity\User;
use App\Repository\ReviewRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Serializer\Attribute\Ignore;

#[ORM\Entity(repositoryClass: ReviewRepository::class)]
#[ORM\HasLifecycleCallbacks]
#[ApiResource(
    operations: [
        new Get(),
        new GetCollection(), // TODO удалить этот вход
        new GetCollection(uriTemplate: 'reviews/me'),
        new GetCollection(uriTemplate: 'reviews/masters/{id}'),
        new GetCollection(uriTemplate: 'reviews/clients/{id}'),
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
    normalizationContext: [
        'groups' => ['reviews:read'],
        'skip_null_values' => false,
    ],
    paginationEnabled: false,
)]
class Review
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

    #[ORM\Column(type: 'boolean', nullable: true)]
    #[Groups([
        'reviews:read',
    ])]
    private ?bool $forReviewer = null;

    #[ORM\ManyToOne(inversedBy: 'reviews')]
    #[ORM\JoinColumn(name: 'services_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    #[Ignore]
    private ?Ticket $services = null;

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

    public function getForReviewer(): ?bool
    {
        return $this->forReviewer;
    }

    public function setForReviewer(?bool $forReviewer): Review
    {
        $this->forReviewer = $forReviewer;
        return $this;
    }

    public function getServices(): ?Ticket
    {
        return $this->services;
    }

    public function setServices(?Ticket $services): static
    {
        $this->services = $services;

        return $this;
    }
}
