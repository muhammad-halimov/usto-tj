<?php

namespace App\Entity\Service;

use App\Entity\Traits\CreatedAtTrait;
use App\Entity\Traits\UpdatedAtTrait;
use App\Repository\UserServiceUnitRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: UserServiceUnitRepository::class)]
#[ORM\HasLifecycleCallbacks]
class UserServiceUnit
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

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $title = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description = null;

    /**
     * @var Collection<int, UserService>
     */
    #[ORM\OneToMany(targetEntity: UserService::class, mappedBy: 'userServiceUnit')]
    private Collection $ыservice;

    public function __construct()
    {
        $this->ыservice = new ArrayCollection();
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

    /**
     * @return Collection<int, UserService>
     */
    public function getыservice(): Collection
    {
        return $this->ыservice;
    }

    public function addService(UserService $service): static
    {
        if (!$this->ыservice->contains($service)) {
            $this->ыservice->add($service);
            $service->setUserServiceUnit($this);
        }

        return $this;
    }

    public function removeService(UserService $service): static
    {
        if ($this->ыservice->removeElement($service)) {
            // set the owning side to null (unless already changed)
            if ($service->getUserServiceUnit() === $this) {
                $service->setUserServiceUnit(null);
            }
        }

        return $this;
    }
}
