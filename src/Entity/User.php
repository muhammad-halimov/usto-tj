<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use App\Entity\Chat\UserServiceChat;
use App\Entity\Chat\UserServiceChatMessage;
use App\Entity\Service\Gallery\UserServiceGallery;
use App\Entity\Service\UserService;
use App\Entity\Traits\CreatedAtTrait;
use App\Entity\Traits\UpdatedAtTrait;
use App\Repository\UserRepository;
use DateTime;
use Deprecated;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Validator\Constraints as Assert;
use Vich\UploaderBundle\Mapping\Annotation as Vich;

#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ORM\Table(name: '`user`')]
#[Vich\Uploadable]
#[ORM\HasLifecycleCallbacks]
#[ORM\UniqueConstraint(name: 'UNIQ_IDENTIFIER_EMAIL', fields: ['email'])]
#[ApiResource]
class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    use UpdatedAtTrait, CreatedAtTrait;

    public const ROLES = [
        'Администратор' => 'ROLE_ADMIN',
        'Мастер' => 'ROLE_MASTER',
        'Клиент' => 'ROLE_CLIENT',
    ];

    public function __toString(): string
    {
        return $this->getEmail();
    }

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 180)]
    private ?string $email = null;

    #[ORM\Column(length: 32)]
    private ?string $name = null;

    #[ORM\Column(length: 32)]
    private ?string $surname = null;

    #[ORM\Column(length: 32, nullable: true)]
    private ?string $patronymic = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $bio = null;

    #[ORM\Column(type: 'float', nullable: true)]
    private ?float $rating = null;

    #[Vich\UploadableField(mapping: 'profile_photos', fileNameProperty: 'image')]
    #[Assert\Image(mimeTypes: ['image/png', 'image/jpeg', 'image/jpg', 'image/webp'])]
    private ?File $imageFile = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $image = null;

    /**
     * @var list<string> The user roles
     */
    #[ORM\Column]
    private array $roles = [];

    /**
     * @var string|null The hashed password
     */
    #[ORM\Column]
    private ?string $password = null;

    private ?string $plainPassword = null;

    /**
     * @var Collection<int, UserSocialNetwork>
     */
    #[ORM\OneToMany(targetEntity: UserSocialNetwork::class, mappedBy: 'user', cascade: ['all'])]
    private Collection $userSocialNetworks;

    /**
     * @var Collection<int, UserServiceGallery>
     */
    #[ORM\ManyToMany(targetEntity: UserServiceGallery::class, mappedBy: 'user')]
    private Collection $userServiceGalleries;

    /**
     * @var Collection<int, UserServiceReview>
     */
    #[ORM\OneToMany(targetEntity: UserServiceReview::class, mappedBy: 'user')]
    private Collection $userServiceReviews;

    /**
     * @var Collection<int, UserService>
     */
    #[ORM\OneToMany(targetEntity: UserService::class, mappedBy: 'user')]
    private Collection $userServices;

    /**
     * @var Collection<int, UserServiceChat>
     */
    #[ORM\OneToMany(targetEntity: UserServiceChat::class, mappedBy: 'messageAuthor')]
    private Collection $userServiceChats;

    #[ORM\ManyToOne(inversedBy: 'author')]
    private ?UserServiceChatMessage $userServiceChatMessage = null;

    public function __construct()
    {
        $this->userSocialNetworks = new ArrayCollection();
        $this->userServiceGalleries = new ArrayCollection();
        $this->userServiceReviews = new ArrayCollection();
        $this->userServices = new ArrayCollection();
        $this->userServiceChats = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(string $email): static
    {
        $this->email = $email;

        return $this;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(?string $name): User
    {
        $this->name = $name;
        return $this;
    }

    public function getSurname(): ?string
    {
        return $this->surname;
    }

    public function setSurname(?string $surname): User
    {
        $this->surname = $surname;
        return $this;
    }

    public function getPatronymic(): ?string
    {
        return $this->patronymic;
    }

    public function setPatronymic(?string $patronymic): User
    {
        $this->patronymic = $patronymic;
        return $this;
    }

    public function getBio(): ?string
    {
        return $this->bio;
    }

    public function setBio(?string $bio): User
    {
        $this->bio = $bio;
        return $this;
    }

    public function getRating(): ?float
    {
        return $this->rating;
    }

    public function setRating(?float $rating): User
    {
        $this->rating = $rating;
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
     * A visual identifier that represents this user.
     *
     * @see UserInterface
     */
    public function getUserIdentifier(): string
    {
        return (string) $this->email;
    }

    /**
     * @see UserInterface
     */
    public function getRoles(): array
    {
        $roles = $this->roles;
        // guarantee every user at least has ROLE_USER
        $roles[] = 'ROLE_USER';

        return array_unique($roles);
    }

    /**
     * @param list<string> $roles
     */
    public function setRoles(array $roles): static
    {
        $this->roles = $roles;

        return $this;
    }

    /**
     * @see PasswordAuthenticatedUserInterface
     */
    public function getPassword(): ?string
    {
        return $this->password;
    }

    public function setPassword(string $password): static
    {
        $this->password = $password;

        return $this;
    }

    /**
     * @return string|null
     */
    public function getPlainPassword(): ?string
    {
        return $this->plainPassword;
    }

    /**
     * @param string|null $plainPassword
     */
    public function setPlainPassword(?string $plainPassword): void
    {
        $this->plainPassword = $plainPassword;
    }

    /**
     * Ensure the session doesn't contain actual password hashes by CRC32C-hashing them, as supported since Symfony 7.3.
     */
    public function __serialize(): array
    {
        $data = (array) $this;
        $data["\0".self::class."\0password"] = hash('crc32c', $this->password);

        return $data;
    }

    #[Deprecated]
    public function eraseCredentials(): void
    {
        // @deprecated, to be removed when upgrading to Symfony 8
    }

    /**
     * @return Collection<int, UserSocialNetwork>
     */
    public function getUserSocialNetworks(): Collection
    {
        return $this->userSocialNetworks;
    }

    public function addUserSocialNetwork(UserSocialNetwork $userSocialNetwork): static
    {
        if (!$this->userSocialNetworks->contains($userSocialNetwork)) {
            $this->userSocialNetworks->add($userSocialNetwork);
            $userSocialNetwork->setUser($this);
        }

        return $this;
    }

    public function removeUserSocialNetwork(UserSocialNetwork $userSocialNetwork): static
    {
        if ($this->userSocialNetworks->removeElement($userSocialNetwork)) {
            // set the owning side to null (unless already changed)
            if ($userSocialNetwork->getUser() === $this) {
                $userSocialNetwork->setUser(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, UserServiceGallery>
     */
    public function getUserServiceGalleries(): Collection
    {
        return $this->userServiceGalleries;
    }

    public function addUserServiceGallery(UserServiceGallery $userServiceGallery): static
    {
        if (!$this->userServiceGalleries->contains($userServiceGallery)) {
            $this->userServiceGalleries->add($userServiceGallery);
            $userServiceGallery->addUser($this);
        }

        return $this;
    }

    public function removeUserServiceGallery(UserServiceGallery $userServiceGallery): static
    {
        if ($this->userServiceGalleries->removeElement($userServiceGallery)) {
            $userServiceGallery->removeUser($this);
        }

        return $this;
    }

    /**
     * @return Collection<int, UserServiceReview>
     */
    public function getUserServiceReviews(): Collection
    {
        return $this->userServiceReviews;
    }

    public function addUserServiceReview(UserServiceReview $userServiceReview): static
    {
        if (!$this->userServiceReviews->contains($userServiceReview)) {
            $this->userServiceReviews->add($userServiceReview);
            $userServiceReview->setUser($this);
        }

        return $this;
    }

    public function removeUserServiceReview(UserServiceReview $userServiceReview): static
    {
        if ($this->userServiceReviews->removeElement($userServiceReview)) {
            // set the owning side to null (unless already changed)
            if ($userServiceReview->getUser() === $this) {
                $userServiceReview->setUser(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, UserService>
     */
    public function getUserServices(): Collection
    {
        return $this->userServices;
    }

    public function addUserService(UserService $userService): static
    {
        if (!$this->userServices->contains($userService)) {
            $this->userServices->add($userService);
            $userService->setUser($this);
        }

        return $this;
    }

    public function removeUserService(UserService $userService): static
    {
        if ($this->userServices->removeElement($userService)) {
            // set the owning side to null (unless already changed)
            if ($userService->getUser() === $this) {
                $userService->setUser(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, UserServiceChat>
     */
    public function getUserServiceChats(): Collection
    {
        return $this->userServiceChats;
    }

    public function addUserServiceChat(UserServiceChat $userServiceChat): static
    {
        if (!$this->userServiceChats->contains($userServiceChat)) {
            $this->userServiceChats->add($userServiceChat);
            $userServiceChat->setMessageAuthor($this);
        }

        return $this;
    }

    public function removeUserServiceChat(UserServiceChat $userServiceChat): static
    {
        if ($this->userServiceChats->removeElement($userServiceChat)) {
            // set the owning side to null (unless already changed)
            if ($userServiceChat->getMessageAuthor() === $this) {
                $userServiceChat->setMessageAuthor(null);
            }
        }

        return $this;
    }

    public function getUserServiceChatMessage(): ?UserServiceChatMessage
    {
        return $this->userServiceChatMessage;
    }

    public function setUserServiceChatMessage(?UserServiceChatMessage $userServiceChatMessage): static
    {
        $this->userServiceChatMessage = $userServiceChatMessage;

        return $this;
    }
}
