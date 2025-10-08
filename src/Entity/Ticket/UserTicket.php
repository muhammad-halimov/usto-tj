<?php

namespace App\Entity\Ticket;

use ApiPlatform\Metadata\ApiResource;
use App\Entity\Service\UserServiceCategory;
use App\Entity\Traits\CreatedAtTrait;
use App\Entity\Traits\UpdatedAtTrait;
use App\Entity\User;
use App\Repository\UserTicketRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: UserTicketRepository::class)]
#[ORM\HasLifecycleCallbacks]
#[ApiResource]
class UserTicket
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

    #[ORM\Column(length: 64, nullable: true)]
    private ?string $title = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $notice = null;

    #[ORM\Column(nullable: true)]
    private ?float $budget = null;

    #[ORM\ManyToOne(inversedBy: 'userTickets')]
    private ?UserServiceCategory $category = null;

    #[ORM\ManyToOne(inversedBy: 'userTickets')]
    private ?User $author = null;

    /**
     * @var Collection<int, UserTicketImage>
     */
    #[ORM\OneToMany(targetEntity: UserTicketImage::class, mappedBy: 'userTicket')]
    private Collection $userTicketImages;

    public function __construct()
    {
        $this->userTicketImages = new ArrayCollection();
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

    public function getNotice(): ?string
    {
        return $this->notice;
    }

    public function setNotice(?string $notice): UserTicket
    {
        $this->notice = $notice;
        return $this;
    }

    public function getBudget(): ?float
    {
        return $this->budget;
    }

    public function setBudget(?float $budget): static
    {
        $this->budget = $budget;

        return $this;
    }

    public function getCategory(): ?UserServiceCategory
    {
        return $this->category;
    }

    public function setCategory(?UserServiceCategory $category): static
    {
        $this->category = $category;

        return $this;
    }

    public function getAuthor(): ?User
    {
        return $this->author;
    }

    public function setAuthor(?User $author): static
    {
        $this->author = $author;

        return $this;
    }

    /**
     * @return Collection<int, UserTicketImage>
     */
    public function getUserTicketImages(): Collection
    {
        return $this->userTicketImages;
    }

    public function addUserTicketImage(UserTicketImage $userTicketImage): static
    {
        if (!$this->userTicketImages->contains($userTicketImage)) {
            $this->userTicketImages->add($userTicketImage);
            $userTicketImage->setUserTicket($this);
        }

        return $this;
    }

    public function removeUserTicketImage(UserTicketImage $userTicketImage): static
    {
        if ($this->userTicketImages->removeElement($userTicketImage)) {
            // set the owning side to null (unless already changed)
            if ($userTicketImage->getUserTicket() === $this) {
                $userTicketImage->setUserTicket(null);
            }
        }

        return $this;
    }
}
