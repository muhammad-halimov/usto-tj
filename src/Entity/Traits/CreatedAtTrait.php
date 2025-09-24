<?php

namespace App\Entity\Traits;

use DateTime;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Ignore;

#[Orm\HasLifecycleCallbacks]
trait CreatedAtTrait
{
    #[ORM\Column(type: 'datetime', nullable: false)]
    #[Ignore]
    protected DateTime $createdAt;

    public function getCreatedAt(): DateTime
    {
        return $this->createdAt;
    }

    #[ORM\PrePersist]
    public function setCreatedAt(): void
    {
        $this->createdAt = new DateTime();
    }
}
