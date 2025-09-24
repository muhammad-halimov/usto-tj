<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use App\Entity\Service\UserServiceCategory;
use App\Entity\Traits\CreatedAtTrait;
use App\Entity\Traits\UpdatedAtTrait;
use App\Repository\UserTicketRepository;
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

    #[ORM\Column(nullable: true)]
    private ?int $timing = null;

    #[ORM\Column(nullable: true)]
    private ?float $budget = null;

    #[ORM\ManyToOne(inversedBy: 'userTickets')]
    private ?UserServiceCategory $category = null;

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

    public function getTiming(): ?int
    {
        return $this->timing;
    }

    public function setTiming(?int $timing): static
    {
        $this->timing = $timing;

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
}
