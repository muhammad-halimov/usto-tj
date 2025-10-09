<?php

namespace App\Entity\Geography;

use App\Entity\Traits\CreatedAtTrait;
use App\Entity\Traits\UpdatedAtTrait;
use App\Repository\DistrictRepository;
use DateTime;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;
use Vich\UploaderBundle\Mapping\Annotation as Vich;

#[ORM\Entity(repositoryClass: DistrictRepository::class)]
#[ORM\HasLifecycleCallbacks]
#[Vich\Uploadable]
class District
{
    use UpdatedAtTrait, CreatedAtTrait;

    public function __toString(): string
    {
        return $this->title ?? '';
    }

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups([
        'geographies:read',
        'userTickets:read',
    ])]
    private ?int $id = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups([
        'geographies:read',
        'userTickets:read',
    ])]
    private ?string $title = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description = null;

    #[Vich\UploadableField(mapping: 'service_district_photos', fileNameProperty: 'image')]
    #[Assert\Image(mimeTypes: ['image/png', 'image/jpeg', 'image/jpg', 'image/webp'])]
    private ?File $imageFile = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups([
        'geographies:read',
        'userTickets:read',
    ])]
    private ?string $image = null;

    /**
     * @var Collection<int, Geography>
     */
    #[ORM\OneToMany(targetEntity: Geography::class, mappedBy: 'district')]
    private Collection $geographies;

    public function __construct()
    {
        $this->geographies = new ArrayCollection();
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

    public function getImage(): ?string
    {
        return $this->image;
    }

    public function setImage(?string $image): static
    {
        $this->image = $image;

        return $this;
    }

    public function getImageFile(): ?File
    {
        return $this->imageFile;
    }

    public function setImageFile(?File $imageFile): self
    {
        $this->imageFile = $imageFile;
        if (null !== $imageFile) {
            $this->updatedAt = new DateTime();
        }

        return $this;
    }

    /**
     * @return Collection<int, Geography>
     */
    public function getGeographies(): Collection
    {
        return $this->geographies;
    }

    public function addGeography(Geography $geography): static
    {
        if (!$this->geographies->contains($geography)) {
            $this->geographies->add($geography);
            $geography->setDistrict($this);
        }

        return $this;
    }

    public function removeGeography(Geography $geography): static
    {
        if ($this->geographies->removeElement($geography)) {
            // set the owning side to null (unless already changed)
            if ($geography->getDistrict() === $this) {
                $geography->setDistrict(null);
            }
        }

        return $this;
    }
}
