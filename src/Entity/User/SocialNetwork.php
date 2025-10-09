<?php

namespace App\Entity\User;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use App\Entity\Traits\CreatedAtTrait;
use App\Entity\Traits\UpdatedAtTrait;
use App\Entity\User;
use App\Repository\SocialNetworkRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;

#[ORM\Entity(repositoryClass: SocialNetworkRepository::class)]
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
        'social:read',
    ]],
    paginationEnabled: false,
)]
class SocialNetwork
{
    use UpdatedAtTrait, CreatedAtTrait;

    public function __toString(): string
    {
        return $this->network;
    }

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups([
        'social:read',
        'masters:read',
        'clients:read',
    ])]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'userSocialNetworks')]
    private ?User $user = null;

    #[ORM\Column(length: 32, nullable: true)]
    #[Groups([
        'social:read',
        'masters:read',
        'clients:read',
    ])]
    private ?string $network = null;

    #[ORM\Column(length: 64, nullable: true)]
    #[Groups([
        'masters:read',
        'clients:read',
    ])]
    private ?string $handle = null;

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

    public function getNetwork(): ?string
    {
        return $this->network;
    }

    public function setNetwork(?string $network): static
    {
        $this->network = $network;

        return $this;
    }

    public function getHandle(): ?string
    {
        return $this->handle;
    }

    public function setHandle(?string $handle): static
    {
        $this->handle = $handle;

        return $this;
    }
}
