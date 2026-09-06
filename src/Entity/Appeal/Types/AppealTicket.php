<?php

namespace App\Entity\Appeal\Types;

use App\Service\Extra\UuidUtil;

use App\Entity\Appeal\Appeal\Appeal;
use App\Repository\Appeal\AppealTicketRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: AppealTicketRepository::class)]
class AppealTicket extends Appeal
{
    public function __construct()
    {
        parent::__construct();
        $this->setType('ticket');
    }

    public function __toString(): string
    {
        $title = $this->getTitle();
        $id    = UuidUtil::short($this->getId());
        return "#$id Жалоба на услугу/объявление" . ($title ? ": $title" : '');
    }
}
