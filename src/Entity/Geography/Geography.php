<?php

namespace App\Entity\Geography;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use App\Entity\Ticket\Ticket;
use App\Entity\Traits\CreatedAtTrait;
use App\Entity\Traits\UpdatedAtTrait;
use App\Repository\GeographyRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;

#[ORM\Entity(repositoryClass: GeographyRepository::class)]
#[ORM\HasLifecycleCallbacks]
#[ApiResource(
    operations: [
        new Get(),
        new GetCollection(),
        new Post(),
        new Patch(security:
            "is_granted('ROLE_ADMIN')"
        ),
        new Delete(security:
            "is_granted('ROLE_ADMIN')"
        )
    ],
    normalizationContext: ['groups' => [
        'geographies:read',
    ]],
    paginationEnabled: false,
)]
class Geography
{
    use UpdatedAtTrait, CreatedAtTrait;

    public function __toString(): string
    {
        return "г. $this->city - р. $this->district";
    }

    public function __construct()
    {
        $this->tickets = new ArrayCollection();
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
    #[Groups([
        'geographies:read',
        'userTickets:read',
    ])]
    private ?int $id = null;

    #[ORM\Column(length: 64, nullable: true)]
    #[Groups([
        'geographies:read',
        'userTickets:read',
    ])]
    private ?string $province = null;

    #[ORM\Column(type: 'text', nullable: true)]
    #[Groups([
        'geographies:read',
    ])]
    private ?string $description = null;

    /**
     * @var Collection<int, Ticket>
     */
    #[ORM\OneToMany(targetEntity: Ticket::class, mappedBy: 'place')]
    private Collection $tickets;

    #[ORM\ManyToOne(inversedBy: 'geographies')]
    #[Groups([
        'geographies:read',
        'userTickets:read',
    ])]
    private ?City $city = null;

    #[ORM\ManyToOne(inversedBy: 'geographies')]
    #[Groups([
        'geographies:read',
        'userTickets:read',
    ])]
    private ?District $district = null;

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

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): Geography
    {
        $this->description = $description;
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
            $ticket->setPlace($this);
        }

        return $this;
    }

    public function removeTicket(Ticket $ticket): static
    {
        if ($this->tickets->removeElement($ticket)) {
            // set the owning side to null (unless already changed)
            if ($ticket->getPlace() === $this) {
                $ticket->setPlace(null);
            }
        }

        return $this;
    }

    public function getCity(): ?City
    {
        return $this->city;
    }

    public function setCity(?City $city): static
    {
        $this->city = $city;

        return $this;
    }

    public function getDistrict(): ?District
    {
        return $this->district;
    }

    public function setDistrict(?District $district): static
    {
        $this->district = $district;

        return $this;
    }
}
