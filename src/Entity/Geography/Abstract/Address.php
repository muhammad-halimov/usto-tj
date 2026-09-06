<?php
namespace App\Entity\Geography\Abstract;

use App\Entity\Geography\City\City;
use App\Service\Extra\UuidUtil;
use App\Entity\Geography\City\Suburb;
use App\Entity\Geography\District\Community;
use App\Entity\Geography\District\District;
use App\Entity\Geography\District\Settlement;
use App\Entity\Geography\District\Village;
use App\Entity\Geography\Province\Province;
use App\Entity\Ticket\Ticket;
use App\Entity\Trait\NonReadable\CreatedAtTrait;
use App\Entity\Trait\NonReadable\UpdatedAtTrait;
use App\Entity\Trait\Readable\G;
use App\Entity\User;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\IdGenerator\UuidGenerator;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Serializer\Attribute\Ignore;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

#[ORM\HasLifecycleCallbacks]
#[ORM\Entity]
class Address
{
    use UpdatedAtTrait, CreatedAtTrait;

    public function __toString(): string
    {
        // Collection::first() возвращает false (не null) на пустой коллекции,
        // а ->getTranslations()->first()->getTitle() падал с фатальной ошибкой
        // "Call to a member function getTitle() on bool", если у province/city/…
        // ещё не было ни одного перевода. AddressComponent::__toString() уже
        // умеет это безопасно (проверяет isEmpty()) — переиспользуем его вместо
        // повторения той же логики здесь.
        $parts = [];

        if ($this->province)   $parts[] = (string) $this->province;
        if ($this->city)       $parts[] = (string) $this->city;
        if ($this->district)   $parts[] = (string) $this->district;
        if ($this->suburb)     $parts[] = (string) $this->suburb;
        if ($this->settlement) $parts[] = (string) $this->settlement;
        if ($this->community)  $parts[] = (string) $this->community;
        if ($this->village)    $parts[] = (string) $this->village;

        $label = !empty($parts) ? implode(', ', $parts) : 'Адрес';

        return '#' . ($this->id !== null ? UuidUtil::short($this->id) : 'новый') . " $label";
    }

    public function __toArray(): array
    {
        return [
            'province' => $this->province?->__toArray(),
            'district' => $this->district?->__toArray(),
            'city' => $this->city?->__toArray(),
            'settlement' => $this->settlement?->__toArray(),
            'community' => $this->community?->__toArray(),
            'village' => $this->village?->__toArray(),
            'suburb' => $this->suburb?->__toArray(),
        ];
    }

    public function __construct()
    {
        $this->users = new ArrayCollection();
        $this->tickets = new ArrayCollection();
    }

    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: UuidGenerator::class)]
    #[ORM\Column(type: 'uuid', unique: true)]
    #[Groups([
        G::USER_PUBLIC,
        G::MASTERS,
        G::CLIENTS,

        G::REVIEWS,
        G::REVIEWS_CLIENT,
        G::GALLERIES,

        G::MASTER_TICKETS,
        G::CLIENT_TICKETS,

        G::CHATS,
        G::CHAT_MESSAGES,

        G::APPEAL_TICKET,
        G::FAVORITES,
        G::BLACK_LISTS,

        G::TECH_SUPPORT,
        G::TECH_SUPPORT_MESSAGES,
    ])]
    private ?Uuid $id = null;

    #[ORM\ManyToOne(targetEntity: Province::class)]
    #[Groups([
        G::USER_PUBLIC,
        G::MASTERS,
        G::CLIENTS,

        G::REVIEWS,
        G::REVIEWS_CLIENT,
        G::GALLERIES,

        G::MASTER_TICKETS,
        G::CLIENT_TICKETS,

        G::CHATS,
        G::CHAT_MESSAGES,

        G::APPEAL_TICKET,
        G::FAVORITES,
        G::BLACK_LISTS,

        G::TECH_SUPPORT,
        G::TECH_SUPPORT_MESSAGES,
    ])]
    private ?Province $province = null;

    #[ORM\ManyToOne(City::class)]
    #[Groups([
        G::USER_PUBLIC,
        G::MASTERS,
        G::CLIENTS,

        G::REVIEWS,
        G::REVIEWS_CLIENT,
        G::GALLERIES,

        G::MASTER_TICKETS,
        G::CLIENT_TICKETS,

        G::CHATS,
        G::CHAT_MESSAGES,

        G::APPEAL_TICKET,
        G::FAVORITES,
        G::BLACK_LISTS,

        G::TECH_SUPPORT,
        G::TECH_SUPPORT_MESSAGES,
    ])]
    private ?City $city = null;

    #[ORM\ManyToOne(targetEntity: Suburb::class)]
    #[Groups([
        G::USER_PUBLIC,
        G::MASTERS,
        G::CLIENTS,

        G::REVIEWS,
        G::REVIEWS_CLIENT,
        G::GALLERIES,

        G::MASTER_TICKETS,
        G::CLIENT_TICKETS,

        G::CHATS,
        G::CHAT_MESSAGES,

        G::APPEAL_TICKET,
        G::FAVORITES,
        G::BLACK_LISTS,

        G::TECH_SUPPORT,
        G::TECH_SUPPORT_MESSAGES,
    ])]
    private ?Suburb $suburb = null;

    #[ORM\ManyToOne(targetEntity: District::class)]
    #[Groups([
        G::USER_PUBLIC,
        G::MASTERS,
        G::CLIENTS,

        G::REVIEWS,
        G::REVIEWS_CLIENT,
        G::GALLERIES,

        G::MASTER_TICKETS,
        G::CLIENT_TICKETS,

        G::CHATS,
        G::CHAT_MESSAGES,

        G::APPEAL_TICKET,
        G::FAVORITES,
        G::BLACK_LISTS,

        G::TECH_SUPPORT,
        G::TECH_SUPPORT_MESSAGES,
    ])]
    private ?District $district = null;

    #[ORM\ManyToOne(Settlement::class)]
    #[Groups([
        G::USER_PUBLIC,
        G::MASTERS,
        G::CLIENTS,

        G::REVIEWS,
        G::REVIEWS_CLIENT,
        G::GALLERIES,

        G::MASTER_TICKETS,
        G::CLIENT_TICKETS,

        G::CHATS,
        G::CHAT_MESSAGES,

        G::APPEAL_TICKET,
        G::FAVORITES,
        G::BLACK_LISTS,

        G::TECH_SUPPORT,
        G::TECH_SUPPORT_MESSAGES,
    ])]
    private ?Settlement $settlement = null;

    #[ORM\ManyToOne(Community::class)]
    #[Groups([
        G::USER_PUBLIC,
        G::MASTERS,
        G::CLIENTS,

        G::REVIEWS,
        G::REVIEWS_CLIENT,
        G::GALLERIES,

        G::MASTER_TICKETS,
        G::CLIENT_TICKETS,

        G::CHATS,
        G::CHAT_MESSAGES,

        G::APPEAL_TICKET,
        G::FAVORITES,
        G::BLACK_LISTS,

        G::TECH_SUPPORT,
        G::TECH_SUPPORT_MESSAGES,
    ])]
    private ?Community $community = null;

    #[ORM\ManyToOne(Village::class)]
    #[Groups([
        G::USER_PUBLIC,
        G::MASTERS,
        G::CLIENTS,

        G::REVIEWS,
        G::REVIEWS_CLIENT,
        G::GALLERIES,

        G::MASTER_TICKETS,
        G::CLIENT_TICKETS,

        G::CHATS,
        G::CHAT_MESSAGES,

        G::APPEAL_TICKET,
        G::FAVORITES,
        G::BLACK_LISTS,

        G::TECH_SUPPORT,
        G::TECH_SUPPORT_MESSAGES,
    ])]
    private ?Village $village = null;

    /**
     * @var Collection<int, User>
     */
    #[ORM\ManyToMany(targetEntity: User::class, inversedBy: 'addresses')]
    #[Ignore]
    private Collection $users;

    /**
     * @var Collection<int, Ticket>
     */
    #[ORM\ManyToMany(targetEntity: Ticket::class, mappedBy: 'addresses')]
    #[Ignore]
    private Collection $tickets;

    public function getId(): ?Uuid { return $this->id; }
    public function getProvince(): ?Province { return $this->province; }
    public function setProvince(?Province $province): self { $this->province = $province; return $this; }
    public function getDistrict(): ?District { return $this->district; }
    public function setDistrict(?District $district): self { $this->district = $district; return $this; }
    public function getCity(): ?City { return $this->city; }
    public function setCity(?City $city): self { $this->city = $city; return $this; }
    public function getSettlement(): ?Settlement { return $this->settlement; }
    public function setSettlement(?Settlement $settlement): self { $this->settlement = $settlement; return $this; }
    public function getCommunity(): ?Community { return $this->community; }
    public function setCommunity(?Community $community): self { $this->community = $community; return $this; }
    public function getVillage(): ?Village { return $this->village; }
    public function setVillage(?Village $village): self { $this->village = $village; return $this; }
    public function getSuburb(): ?Suburb { return $this->suburb; }
    public function setSuburb(?Suburb $suburb): self { $this->suburb = $suburb; return $this; }

    /**
     * @return Collection<int, User>
     */
    public function getUsers(): Collection
    {
        return $this->users;
    }
    public function addUser(User $user): static
    {
        if (!$this->users->contains($user)) {
            $this->users->add($user);
        }

        return $this;
    }
    public function removeUser(User $user): static
    {
        $this->users->removeElement($user);

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
            $ticket->addAddress($this);
        }

        return $this;
    }
    public function removeTicket(Ticket $ticket): static
    {
        if ($this->tickets->removeElement($ticket)) {
            $ticket->removeAddress($this);
        }

        return $this;
    }

    /**
     * Гарантирует, что вся адресная иерархия внутренне непротиворечива —
     * то же самое условие, что уже проверяет AddressValidationTrait::
     * buildAndValidateAddresses() при создании/правке адреса тикета через
     * API (POST/PATCH /tickets). Но та проверка живёт в контроллере и
     * срабатывает только для этого конкретного пути — этот Callback
     * гарантирует то же самое на уровне самой сущности, то есть везде, где
     * Address проходит через Symfony Validator. В первую очередь это
     * закрывает реальную дыру в EasyAdmin (AddressCrudController): там
     * province/city/district/... — обычные AssociationField с autocomplete()
     * без всякой сверки друг с другом, поэтому можно было сохранить
     * "Согдийская область" + город "Вахдат" (который на самом деле относится
     * к другой области) — валидатор такое отклонит с тем же текстом ошибки,
     * что уже видит клиент API (см. AppMessages::CITY_NOT_IN_PROVINCE и
     * соседние — messages['ru'] переиспользованы здесь дословно).
     */
    #[Assert\Callback]
    public function validateGeographyHierarchy(ExecutionContextInterface $context): void
    {
        if ($this->city !== null && !UuidUtil::same($this->city->getProvince()?->getId(), $this->province?->getId())) {
            $context->buildViolation('Город не принадлежит указанному региону')
                ->atPath('city')
                ->addViolation();
        }

        if ($this->suburb !== null && !UuidUtil::same($this->suburb->getCities()?->getId(), $this->city?->getId())) {
            $context->buildViolation('Подрайон не принадлежит указанному городу')
                ->atPath('suburb')
                ->addViolation();
        }

        if ($this->district !== null && !UuidUtil::same($this->district->getProvince()?->getId(), $this->province?->getId())) {
            $context->buildViolation('Район не принадлежит указанному региону')
                ->atPath('district')
                ->addViolation();
        }

        if ($this->community !== null && !UuidUtil::same($this->community->getDistrict()?->getId(), $this->district?->getId())) {
            $context->buildViolation('Джамоат не принадлежит указанному району')
                ->atPath('community')
                ->addViolation();
        }

        if ($this->settlement !== null && !UuidUtil::same($this->settlement->getDistrict()?->getId(), $this->district?->getId())) {
            $context->buildViolation('Населённый пункт не принадлежит указанному району')
                ->atPath('settlement')
                ->addViolation();
        }

        if ($this->village !== null) {
            if ($this->settlement === null) {
                $context->buildViolation('Требуется населённый пункт при указании деревни')
                    ->atPath('village')
                    ->addViolation();
            } elseif (!UuidUtil::same($this->village->getSettlement()?->getId(), $this->settlement->getId())) {
                $context->buildViolation('Деревня не принадлежит указанному населённому пункту')
                    ->atPath('village')
                    ->addViolation();
            }
        }
    }
}
