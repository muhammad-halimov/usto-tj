<?php

namespace App\Entity\Appeal\Types;

use App\Service\Extra\UuidUtil;

use App\Entity\Appeal\Appeal\Appeal;
use App\Repository\Appeal\AppealUserRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: AppealUserRepository::class)]
class AppealUser extends Appeal
{
    public function __construct()
    {
        parent::__construct();
        $this->setType('user');
    }

    public function __toString(): string
    {
        $title = $this->getTitle();
        $id    = UuidUtil::short($this->getId());
        return "#$id Жалоба на пользователя" . ($title ? ": $title" : '');
    }
}
