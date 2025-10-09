<?php

namespace App\Entity\Chat;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use App\Entity\Traits\CreatedAtTrait;
use App\Entity\Traits\UpdatedAtTrait;
use App\Entity\User;
use App\Repository\Chat\ChatRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;

#[ORM\HasLifecycleCallbacks]
#[ORM\Entity(repositoryClass: ChatRepository::class)]
#[ApiResource(
    operations: [
        new Get(),
        new GetCollection(uriTemplate: 'chats/me'),
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
    normalizationContext: ['groups' => [
        'chats:read',
    ]],
    paginationEnabled: false,
)]class Chat
{
    use UpdatedAtTrait, CreatedAtTrait;

    public function __toString(): string
    {
        return "Chat ID: $this->id";
    }

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups([
        'chats:read',
    ])]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'userServiceChats')]
    #[Groups([
        'chats:read',
    ])]
    private ?User $messageAuthor = null;

    #[ORM\ManyToOne(inversedBy: 'userServiceChats')]
    #[Groups([
        'chats:read',
    ])]
    private ?User $replyAuthor = null;

    /**
     * @var Collection<int, ChatMessage>
     */
    #[ORM\OneToMany(targetEntity: ChatMessage::class, mappedBy: 'userServiceChat', cascade: ['all'])]
    #[Groups([
        'chats:read',
    ])]
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
     * @return Collection<int, ChatMessage>
     */
    public function getMessage(): Collection
    {
        return $this->message;
    }

    public function addMessage(ChatMessage $message): static
    {
        if (!$this->message->contains($message)) {
            $this->message->add($message);
            $message->setUserServiceChat($this);
        }

        return $this;
    }

    public function removeMessage(ChatMessage $message): static
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
