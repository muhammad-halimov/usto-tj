<?php

namespace App\Entity\Extra;

use App\Entity\Trait\Readable\CreatedAtTrait;
use App\Entity\Trait\Readable\G;
use App\Entity\Trait\Readable\UpdatedAtTrait;
use App\Entity\User;
use App\Repository\User\UserOAuthProviderRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\IdGenerator\UuidGenerator;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Serializer\Attribute\Groups;

/**
 * Связующая сущность User <-> внешний OAuth-аккаунт (google/facebook/
 * instagram/telegram) — по одной записи на каждую привязку. Создаётся
 * в трёх местах: findOrCreateUser() каждого *OAuthService при
 * логине/регистрации (Google/Facebook/Instagram/Telegram), и в
 * LinkOAuthProviderController при явной привязке доп. провайдера уже
 * залогиненному пользователю.
 *
 * uq_provider_id (provider, providerId) гарантирует на уровне БД, что
 * один и тот же внешний аккаунт не может быть привязан сразу к двум
 * разным User — эта проверка дублируется и в коде (см.
 * UserOAuthProviderRepository::findOneByProviderAndId() +
 * UserRepository::findByOAuthProvider()), но констрейнт — последняя
 * линия защиты от гонки при параллельных запросах.
 */
#[ORM\Entity(repositoryClass: UserOAuthProviderRepository::class)]
#[ORM\Table(name: 'user_oauth_provider')]
#[ORM\UniqueConstraint(name: 'uq_provider_id', columns: ['provider', 'provider_id'])]
#[ORM\HasLifecycleCallbacks]
class OAuthProvider
{
    use UpdatedAtTrait, CreatedAtTrait;

    public function __toString(): string
    {
        if ($this->provider !== '') return "#$this->id $this->provider";

        return $this->providerId !== ''
            ? "#$this->id OAuth provider ID {$this->providerId}"
            : "#$this->id Новый OAuth provider";
    }

    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: UuidGenerator::class)]
    #[ORM\Column(type: 'uuid', unique: true)]
    #[Groups([G::USERS_ME])]
    private ?Uuid $id = null;

    #[ORM\ManyToOne(targetEntity: User::class, inversedBy: 'oauthProviders')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?User $user = null;

    /** google | facebook | instagram | telegram */
    #[ORM\Column(length: 50)]
    #[Groups([G::USERS_ME])]
    private string $provider = '';

    #[ORM\Column(type: 'text')]
    #[Groups([G::USERS_ME])]
    private string $providerId = '';

    public function getId(): ?Uuid
    {
        return $this->id;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(User $user): static
    {
        $this->user = $user;
        return $this;
    }

    public function getProvider(): string
    {
        return $this->provider;
    }

    public function setProvider(string $provider): static
    {
        $this->provider = $provider;
        return $this;
    }

    public function getProviderId(): string
    {
        return $this->providerId;
    }

    public function setProviderId(string $providerId): static
    {
        $this->providerId = $providerId;
        return $this;
    }
}
