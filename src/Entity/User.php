<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use App\Entity\Chat\Chat;
use App\Entity\Chat\ChatMessage;
use App\Entity\Gallery\Gallery;
use App\Entity\Ticket\Ticket;
use App\Entity\Traits\CreatedAtTrait;
use App\Entity\Traits\UpdatedAtTrait;
use App\Entity\User\Education;
use App\Entity\User\Review;
use App\Entity\User\SocialNetwork;
use App\Repository\UserRepository;
use DateTime;
use Deprecated;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Serializer\Attribute\Ignore;
use Symfony\Component\Validator\Constraints as Assert;
use Vich\UploaderBundle\Mapping\Annotation as Vich;

#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ORM\Table(name: '`user`')]
#[Vich\Uploadable]
#[ORM\HasLifecycleCallbacks]
#[ORM\UniqueConstraint(name: 'UNIQ_IDENTIFIER_EMAIL', fields: ['email'])]
#[ApiResource(
    operations: [
        new Get(),
        new GetCollection(),
        new Post(),
        new Patch(security:
            "is_granted('ROLE_ADMIN') or
             is_granted('ROLE_MASTER') or
             is_granted('ROLE_CLIENT')"
        ),
        new Delete(security:
            "is_granted('ROLE_ADMIN') or
             is_granted('ROLE_MASTER') or
             is_granted('ROLE_CLIENT')"
        )
    ],
    normalizationContext: [
        'groups' => ['masters:read', 'clients:read'],
        'skip_null_values' => false,
    ],
    paginationEnabled: false,
)]
class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    use UpdatedAtTrait, CreatedAtTrait;

    public const ROLES = [
        'Администратор' => 'ROLE_ADMIN',
        'Мастер' => 'ROLE_MASTER',
        'Клиент' => 'ROLE_CLIENT',
    ];

    public const GENDERS = [
        'Женский' => 'gender_female',
        'Мужской' => 'gender_male',
        'Не указано' => 'gender_neutral',
    ];

    public function __toString(): string
    {
        return $this->getEmail();
    }

    public function __construct()
    {
        $this->userSocialNetworks = new ArrayCollection();
        $this->userServiceReviews = new ArrayCollection();
        $this->userServiceChats = new ArrayCollection();
        $this->userTickets = new ArrayCollection();
        $this->education = new ArrayCollection();
        $this->chatMessages = new ArrayCollection();
        $this->galleries = new ArrayCollection();
        $this->tickets = new ArrayCollection();
    }

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups([
        'masters:read',
        'clients:read',
        'masterServices:read',
        'reviews:read',
        'galleries:read',
        'userTickets:read',
        'chats:read',
    ])]
    private ?int $id = null;

    #[ORM\Column(length: 180)]
    #[Groups([
        'masters:read',
        'clients:read',
        'masterServices:read',
        'reviews:read',
        'galleries:read',
        'userTickets:read',
        'chats:read',
    ])]
    private ?string $email = null;

    #[ORM\Column(length: 32, nullable: true)]
    #[Groups([
        'masters:read',
        'clients:read',
        'masterServices:read',
        'reviews:read',
        'galleries:read',
        'userTickets:read',
        'chats:read',
    ])]
    private ?string $name = null;

    #[ORM\Column(length: 32, nullable: true)]
    #[Groups([
        'masters:read',
        'clients:read',
        'masterServices:read',
        'reviews:read',
        'galleries:read',
        'userTickets:read',
        'chats:read',
    ])]
    private ?string $surname = null;

    #[ORM\Column(length: 32, nullable: true)]
    #[Groups([
        'masters:read',
        'clients:read',
        'masterServices:read',
    ])]
    private ?string $patronymic = null;

    #[ORM\Column(type: 'text', nullable: true)]
    #[Groups([
        'masters:read',
        'clients:read',
    ])]
    private ?string $bio = null;

    #[ORM\Column(type: 'float', nullable: true)]
    #[Groups([
        'masters:read',
        'clients:read',
        'masterServices:read',
        'reviews:read',
        'userTickets:read',
    ])]
    private ?float $rating = null;

    #[ORM\Column(length: 16, nullable: true)]
    #[Groups([
        'masters:read',
        'clients:read',
    ])]
    private ?string $gender = null;

    #[Vich\UploadableField(mapping: 'profile_photos', fileNameProperty: 'image')]
    #[Assert\Image(mimeTypes: ['image/png', 'image/jpeg', 'image/jpg', 'image/webp'])]
    private ?File $imageFile = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups([
        'masters:read',
        'clients:read',
        'masterServices:read',
        'reviews:read',
        'galleries:read',
        'userTickets:read',
    ])]
    private ?string $image = null;

    #[ORM\Column(length: 15, nullable: true)]
    #[Groups([
        'masters:read',
        'clients:read',
        'masterServices:read',
        'chats:read',
    ])]
    private ?string $phone1 = null;

    #[ORM\Column(length: 15, nullable: true)]
    #[Groups([
        'masters:read',
        'clients:read',
        'masterServices:read',
        'chats:read',
    ])]
    private ?string $phone2 = null;

    /**
     * @var list<string> The user roles
     */
    #[ORM\Column]
    #[Groups([
        'masters:read',
        'clients:read',
    ])]
    private array $roles = [];

    /**
     * @var string|null The hashed password
     */
    #[ORM\Column]
    private ?string $password = null;

    #[Ignore]
    private ?string $plainPassword = null;

    /**
     * @var Collection<int, SocialNetwork>
     */
    #[ORM\OneToMany(targetEntity: SocialNetwork::class, mappedBy: 'user', cascade: ['all'])]
    #[Groups([
        'masters:read',
        'clients:read',
    ])]
    private Collection $userSocialNetworks;

    /**
     * @var Collection<int, Review>
     */
    #[ORM\OneToMany(targetEntity: Review::class, mappedBy: 'user')]
    #[Ignore]
    private Collection $userServiceReviews;

    /**
     * @var Collection<int, Chat>
     */
    #[ORM\OneToMany(targetEntity: Chat::class, mappedBy: 'messageAuthor')]
    #[Ignore]
    private Collection $userServiceChats;

    /**
     * @var Collection<int, Ticket>
     */
    #[ORM\OneToMany(targetEntity: Ticket::class, mappedBy: 'author')]
    #[Ignore]
    private Collection $userTickets;

    /**
     * @var Collection<int, Education>
     */
    #[ORM\OneToMany(targetEntity: Education::class, mappedBy: 'user', cascade: ['all'])]
    #[Groups([
        'masters:read',
    ])]
    private Collection $education;

    /**
     * @var Collection<int, ChatMessage>
     */
    #[ORM\OneToMany(targetEntity: ChatMessage::class, mappedBy: 'author')]
    #[Ignore]
    private Collection $chatMessages;

    /**
     * @var Collection<int, Gallery>
     */
    #[ORM\OneToMany(targetEntity: Gallery::class, mappedBy: 'user')]
    #[Ignore]
    private Collection $galleries;

    /**
     * @var Collection<int, Ticket>
     */
    #[ORM\OneToMany(targetEntity: Ticket::class, mappedBy: 'master')]
    #[Ignore]
    private Collection $tickets;

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

    public function getGender(): ?string
    {
        return $this->gender;
    }

    public function setGender(?string $gender): User
    {
        $this->gender = $gender;
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

    public function getPhone1(): ?string
    {
        return $this->phone1;
    }

    public function setPhone1(?string $phone1): User
    {
        $this->phone1 = $phone1;
        return $this;
    }

    public function getPhone2(): ?string
    {
        return $this->phone2;
    }

    public function setPhone2(?string $phone2): User
    {
        $this->phone2 = $phone2;
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
     * @return Collection<int, SocialNetwork>
     */
    public function getUserSocialNetworks(): Collection
    {
        return $this->userSocialNetworks;
    }

    public function addUserSocialNetwork(SocialNetwork $userSocialNetwork): static
    {
        if (!$this->userSocialNetworks->contains($userSocialNetwork)) {
            $this->userSocialNetworks->add($userSocialNetwork);
            $userSocialNetwork->setUser($this);
        }

        return $this;
    }

    public function removeUserSocialNetwork(SocialNetwork $userSocialNetwork): static
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
     * @return Collection<int, Review>
     */
    public function getUserServiceReviews(): Collection
    {
        return $this->userServiceReviews;
    }

    public function addUserServiceReview(Review $userServiceReview): static
    {
        if (!$this->userServiceReviews->contains($userServiceReview)) {
            $this->userServiceReviews->add($userServiceReview);
            $userServiceReview->setUser($this);
        }

        return $this;
    }

    public function removeUserServiceReview(Review $userServiceReview): static
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
     * @return Collection<int, Chat>
     */
    public function getUserServiceChats(): Collection
    {
        return $this->userServiceChats;
    }

    public function addUserServiceChat(Chat $userServiceChat): static
    {
        if (!$this->userServiceChats->contains($userServiceChat)) {
            $this->userServiceChats->add($userServiceChat);
            $userServiceChat->setMessageAuthor($this);
        }

        return $this;
    }

    public function removeUserServiceChat(Chat $userServiceChat): static
    {
        if ($this->userServiceChats->removeElement($userServiceChat)) {
            // set the owning side to null (unless already changed)
            if ($userServiceChat->getMessageAuthor() === $this) {
                $userServiceChat->setMessageAuthor(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, Ticket>
     */
    public function getUserTickets(): Collection
    {
        return $this->userTickets;
    }

    public function addUserTicket(Ticket $userTicket): static
    {
        if (!$this->userTickets->contains($userTicket)) {
            $this->userTickets->add($userTicket);
            $userTicket->setAuthor($this);
        }

        return $this;
    }

    public function removeUserTicket(Ticket $userTicket): static
    {
        if ($this->userTickets->removeElement($userTicket)) {
            // set the owning side to null (unless already changed)
            if ($userTicket->getAuthor() === $this) {
                $userTicket->setAuthor(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, Education>
     */
    public function getEducation(): Collection
    {
        return $this->education;
    }

    public function addEducation(Education $education): static
    {
        if (!$this->education->contains($education)) {
            $this->education->add($education);
            $education->setUser($this);
        }

        return $this;
    }

    public function removeEducation(Education $education): static
    {
        if ($this->education->removeElement($education)) {
            // set the owning side to null (unless already changed)
            if ($education->getUser() === $this) {
                $education->setUser(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, ChatMessage>
     */
    public function getChatMessages(): Collection
    {
        return $this->chatMessages;
    }

    public function addChatMessage(ChatMessage $chatMessage): static
    {
        if (!$this->chatMessages->contains($chatMessage)) {
            $this->chatMessages->add($chatMessage);
            $chatMessage->setAuthor($this);
        }

        return $this;
    }

    public function removeChatMessage(ChatMessage $chatMessage): static
    {
        if ($this->chatMessages->removeElement($chatMessage)) {
            // set the owning side to null (unless already changed)
            if ($chatMessage->getAuthor() === $this) {
                $chatMessage->setAuthor(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, Gallery>
     */
    public function getGalleries(): Collection
    {
        return $this->galleries;
    }

    public function addGallery(Gallery $gallery): static
    {
        if (!$this->galleries->contains($gallery)) {
            $this->galleries->add($gallery);
            $gallery->setUser($this);
        }

        return $this;
    }

    public function removeGallery(Gallery $gallery): static
    {
        if ($this->galleries->removeElement($gallery)) {
            // set the owning side to null (unless already changed)
            if ($gallery->getUser() === $this) {
                $gallery->setUser(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, Ticket>
     */
    public function getTickets(): Collection
    {
        return $this->tickets;
    }

    public function addTicket(Ticket $ticket): static
    {
        if (!$this->tickets->contains($ticket)) {
            $this->tickets->add($ticket);
            $ticket->setMaster($this);
        }

        return $this;
    }

    public function removeTicket(Ticket $ticket): static
    {
        if ($this->tickets->removeElement($ticket)) {
            // set the owning side to null (unless already changed)
            if ($ticket->getMaster() === $this) {
                $ticket->setMaster(null);
            }
        }

        return $this;
    }
}
