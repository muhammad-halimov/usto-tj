<?php

namespace App\Entity\Gallery;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use App\Entity\Traits\CreatedAtTrait;
use App\Entity\Traits\UpdatedAtTrait;
use App\Entity\User;
use App\Repository\GalleryRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Serializer\Attribute\SerializedName;

#[ORM\Entity(repositoryClass: GalleryRepository::class)]
#[ORM\HasLifecycleCallbacks]
#[ApiResource(
    operations: [
        new Get(),
        new GetCollection(uriTemplate: 'galleries/me'),
        new GetCollection(uriTemplate: 'galleries/master/{id}'),
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
    normalizationContext: [
        'groups' => ['galleries:read'],
        'skip_null_values' => false,
    ],
    paginationEnabled: false,
)]
class Gallery
{
    use UpdatedAtTrait, CreatedAtTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups([
        'galleries:read',
    ])]
    private ?int $id = null;

    /**
     * @var Collection<int, GalleryItem>
     */
    #[ORM\OneToMany(targetEntity: GalleryItem::class, mappedBy: 'gallery')]
    #[Groups([
        'galleries:read',
    ])]
    #[SerializedName('images')]
    private Collection $userServiceGalleryItems;

    #[ORM\ManyToOne(inversedBy: 'galleries')]
    #[Groups([
        'galleries:read',
    ])]
    private ?User $user = null;

    public function __construct()
    {
        $this->userServiceGalleryItems = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    /**
     * @return Collection<int, GalleryItem>
     */
    public function getUserServiceGalleryItems(): Collection
    {
        return $this->userServiceGalleryItems;
    }

    public function addUserServiceGalleryItem(GalleryItem $userServiceGalleryItem): static
    {
        if (!$this->userServiceGalleryItems->contains($userServiceGalleryItem)) {
            $this->userServiceGalleryItems->add($userServiceGalleryItem);
            $userServiceGalleryItem->setGallery($this);
        }

        return $this;
    }

    public function removeUserServiceGalleryItem(GalleryItem $userServiceGalleryItem): static
    {
        if ($this->userServiceGalleryItems->removeElement($userServiceGalleryItem)) {
            // set the owning side to null (unless already changed)
            if ($userServiceGalleryItem->getGallery() === $this) {
                $userServiceGalleryItem->setGallery(null);
            }
        }

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
