<?php

namespace App\Entity\Geography;

use ApiPlatform\Metadata\ApiResource;
use App\Entity\Traits\CreatedAtTrait;
use App\Entity\Traits\UpdatedAtTrait;
use App\Repository\UserServiceGeographyRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: UserServiceGeographyRepository::class)]
#[ORM\HasLifecycleCallbacks]
#[ApiResource]
class UserServiceGeography
{
    use UpdatedAtTrait, CreatedAtTrait;

    public function __toString(): string
    {
        return $this->province;
    }

    public function __construct()
    {
        $this->city = new ArrayCollection();
        $this->district = new ArrayCollection();
    }

    public const PROVINCES = [
        'ГРРП' => 'ГРРП',
        'ГБАО' => 'ГБАО',
        'Согдийская область' => 'Согдийская область',
        'Хатлонская область' => 'Хатлонская область',
    ];

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 64, nullable: true)]
    private ?string $province = null;

    /**
     * @var Collection<int, UserServiceGeographyCity>
     */
    #[ORM\OneToMany(targetEntity: UserServiceGeographyCity::class, mappedBy: 'userServiceGeography')]
    private Collection $city;

    /**
     * @var Collection<int, UserServiceGeographyDistrict>
     */
    #[ORM\OneToMany(targetEntity: UserServiceGeographyDistrict::class, mappedBy: 'userServiceGeography')]
    private Collection $district;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $description = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getProvince(): ?string
    {
        return $this->province;
    }

    public function setProvince(?string $province): static
    {
        $this->province = $province;

        return $this;
    }

    /**
     * @return Collection<int, UserServiceGeographyCity>
     */
    public function getCity(): Collection
    {
        return $this->city;
    }

    public function addCity(UserServiceGeographyCity $city): static
    {
        if (!$this->city->contains($city)) {
            $this->city->add($city);
            $city->setUserServiceGeography($this);
        }

        return $this;
    }

    public function removeCity(UserServiceGeographyCity $city): static
    {
        if ($this->city->removeElement($city)) {
            // set the owning side to null (unless already changed)
            if ($city->getUserServiceGeography() === $this) {
                $city->setUserServiceGeography(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, UserServiceGeographyDistrict>
     */
    public function getDistrict(): Collection
    {
        return $this->district;
    }

    public function addDistrict(UserServiceGeographyDistrict $district): static
    {
        if (!$this->district->contains($district)) {
            $this->district->add($district);
            $district->setUserServiceGeography($this);
        }

        return $this;
    }

    public function removeDistrict(UserServiceGeographyDistrict $district): static
    {
        if ($this->district->removeElement($district)) {
            // set the owning side to null (unless already changed)
            if ($district->getUserServiceGeography() === $this) {
                $district->setUserServiceGeography(null);
            }
        }

        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): UserServiceGeography
    {
        $this->description = $description;
        return $this;
    }
}
