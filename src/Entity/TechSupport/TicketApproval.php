<?php

namespace App\Entity\TechSupport;

use App\Entity\Ticket\Ticket;
use App\Entity\Trait\Readable\CreatedAtTrait;
use App\Entity\Trait\Readable\DescriptionTrait;
use App\Entity\Trait\Readable\UpdatedAtTrait;
use App\Entity\User;
use App\Repository\TechSupport\TicketApprovalRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: TicketApprovalRepository::class)]
#[ORM\HasLifecycleCallbacks]
class TicketApproval
{
    use CreatedAtTrait, UpdatedAtTrait, DescriptionTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(type: 'boolean', options: ['default' => false])]
    private bool $approved = false;

    #[ORM\ManyToOne(inversedBy: 'ticketApprovals')]
    private ?User $administrant = null;

    #[ORM\ManyToOne(inversedBy: 'ticketApprovals')]
    private ?Ticket $ticket = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function isApproved(): bool
    {
        return $this->approved;
    }

    public function setApproved(bool $approved): static
    {
        if ($this->approved === true && $approved === false) {
            throw new \LogicException('Revoking approval is not allowed: approved cannot be reverted back to false.');
        }

        // Если тикет уже забанен, Ticket::setApproved(true) сам откажется
        // применить значение (см. Ticket::setBanned/setApproved) — но без
        // этой проверки approved у самой заявки на подтверждение всё равно
        // проставился бы в true, создавая рассинхрон с реальным состоянием
        // тикета (заявка "одобрена", а тикет как был неодобрен, так и остался).
        if ($approved && $this->ticket?->getBanned()) {
            return $this;
        }

        $this->approved = $approved;

        if ($approved && $this->ticket !== null) {
            $this->ticket->setApproved(true);
        }

        return $this;
    }

    public function getAdministrant(): ?User
    {
        return $this->administrant;
    }

    public function setAdministrant(?User $administrant): static
    {
        $this->administrant = $administrant;

        return $this;
    }

    public function getTicket(): ?Ticket
    {
        return $this->ticket;
    }

    public function setTicket(?Ticket $ticket): static
    {
        $this->ticket = $ticket;

        // Если approval включили до привязки тикета.
        if ($this->approved && $ticket !== null) {
            $ticket->setApproved(true);
        }

        return $this;
    }
}
