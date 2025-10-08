<?php

namespace App\Entity\Service;

use ApiPlatform\Metadata\ApiResource;
use App\Entity\Traits\CreatedAtTrait;
use App\Entity\Traits\UpdatedAtTrait;
use App\Repository\Service\UserServiceImageRepository;
use DateTime;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;
use Vich\UploaderBundle\Mapping\Annotation as Vich;

#[ORM\Entity(repositoryClass: UserServiceImageRepository::class)]
#[ORM\HasLifecycleCallbacks]
#[Vich\Uploadable]
class UserServiceImage
{
    use UpdatedAtTrait, CreatedAtTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups([
        'masterServices:read',
    ])]
    private ?int $id = null;

    #[Vich\UploadableField(mapping: 'user_service_photos', fileNameProperty: 'image')]
    #[Assert\Image(mimeTypes: ['image/png', 'image/jpeg', 'image/jpg', 'image/webp'])]
    private ?File $imageFile = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups([
        'masterServices:read',
    ])]
    private ?string $image = null;

    #[ORM\ManyToOne(inversedBy: 'userServiceImages')]
    private ?UserService $userService = null;

    public function getId(): ?int
    {
        return $this->id;
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

    public function getUserService(): ?UserService
    {
        return $this->userService;
    }

    public function setUserService(?UserService $userService): static
    {
        $this->userService = $userService;

        return $this;
    }
}
