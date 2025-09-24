<?php

namespace App\Entity\Service\Gallery;

use ApiPlatform\Metadata\ApiResource;
use App\Entity\Traits\CreatedAtTrait;
use App\Entity\Traits\UpdatedAtTrait;
use App\Entity\User;
use App\Repository\UserServiceGalleryRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: UserServiceGalleryRepository::class)]
#[ORM\HasLifecycleCallbacks]
#[ApiResource]
class UserServiceGallery
{
    use UpdatedAtTrait, CreatedAtTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    /**
     * @var Collection<int, User>
     */
    #[ORM\ManyToMany(targetEntity: User::class, inversedBy: 'userServiceGalleries')]
    private Collection $user;

    /**
     * @var Collection<int, UserServiceGalleryItem>
     */
    #[ORM\OneToMany(targetEntity: UserServiceGalleryItem::class, mappedBy: 'gallery')]
    private Collection $userServiceGalleryItems;

    public function __construct()
    {
        $this->user = new ArrayCollection();
        $this->userServiceGalleryItems = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    /**
     * @return Collection<int, User>
     */
    public function getUser(): Collection
    {
        return $this->user;
    }

    public function addUser(User $user): static
    {
        if (!$this->user->contains($user)) {
            $this->user->add($user);
        }

        return $this;
    }

    public function removeUser(User $user): static
    {
        $this->user->removeElement($user);

        return $this;
    }

    /**
     * @return Collection<int, UserServiceGalleryItem>
     */
    public function getUserServiceGalleryItems(): Collection
    {
        return $this->userServiceGalleryItems;
    }

    public function addUserServiceGalleryItem(UserServiceGalleryItem $userServiceGalleryItem): static
    {
        if (!$this->userServiceGalleryItems->contains($userServiceGalleryItem)) {
            $this->userServiceGalleryItems->add($userServiceGalleryItem);
            $userServiceGalleryItem->setGallery($this);
        }

        return $this;
    }

    public function removeUserServiceGalleryItem(UserServiceGalleryItem $userServiceGalleryItem): static
    {
        if ($this->userServiceGalleryItems->removeElement($userServiceGalleryItem)) {
            // set the owning side to null (unless already changed)
            if ($userServiceGalleryItem->getGallery() === $this) {
                $userServiceGalleryItem->setGallery(null);
            }
        }

        return $this;
    }
}
