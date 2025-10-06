<?php

namespace App\Entity\Chat;

use App\Entity\Traits\CreatedAtTrait;
use App\Entity\Traits\UpdatedAtTrait;
use App\Entity\User;
use App\Repository\Chat\UserServiceChatRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\HasLifecycleCallbacks]
#[ORM\Entity(repositoryClass: UserServiceChatRepository::class)]
class UserServiceChat
{
    use UpdatedAtTrait, CreatedAtTrait;

    public function __toString(): string
    {
        return "Chat ID: $this->id";
    }

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'userServiceChats')]
    private ?User $messageAuthor = null;

    #[ORM\ManyToOne(inversedBy: 'userServiceChats')]
    private ?User $replyAuthor = null;

    /**
     * @var Collection<int, UserServiceChatMessage>
     */
    #[ORM\OneToMany(targetEntity: UserServiceChatMessage::class, mappedBy: 'userServiceChat')]
    private Collection $message;

    public function __construct()
    {
        $this->message = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getMessageAuthor(): ?User
    {
        return $this->messageAuthor;
    }

    public function setMessageAuthor(?User $messageAuthor): static
    {
        $this->messageAuthor = $messageAuthor;

        return $this;
    }

    public function getReplyAuthor(): ?User
    {
        return $this->replyAuthor;
    }

    public function setReplyAuthor(?User $replyAuthor): static
    {
        $this->replyAuthor = $replyAuthor;

        return $this;
    }

    /**
     * @return Collection<int, UserServiceChatMessage>
     */
    public function getMessage(): Collection
    {
        return $this->message;
    }

    public function addMessage(UserServiceChatMessage $message): static
    {
        if (!$this->message->contains($message)) {
            $this->message->add($message);
            $message->setUserServiceChat($this);
        }

        return $this;
    }

    public function removeMessage(UserServiceChatMessage $message): static
    {
        if ($this->message->removeElement($message)) {
            // set the owning side to null (unless already changed)
            if ($message->getUserServiceChat() === $this) {
                $message->setUserServiceChat(null);
            }
        }

        return $this;
    }
}
