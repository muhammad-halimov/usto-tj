<?php

namespace App\Entity\Extra;

use ApiPlatform\Doctrine\Orm\Filter\SearchFilter;
use ApiPlatform\Metadata\ApiFilter;
use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use App\Entity\Trait\Readable\CreatedAtTrait;
use App\Entity\Trait\Readable\G;
use App\Entity\User;
use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;

/**
 * Неизменяемый след правки/удаления версионируемой сущности — снапшот
 * предыдущего состояния изменённых полей (action=updated) или удалённых
 * данных (action=deleted), плюс кто и когда это сделал.
 *
 * Один тип на ВСЕ версионируемые сущности (Ticket и далее по мере
 * расширения — ChatMessage/TechSupportMessage/Review/MultipleImage),
 * вместо отдельной Revision-сущности под каждую: entityType/entityId —
 * дискриминатор, snapshot — произвольный JSON, форма которого зависит от
 * того, что версионирует конкретный листенер. См. TicketListener — первый
 * (и пока единственный) писатель.
 *
 * Физически неизменяема: у ApiResource нет Post/Patch/Delete — только
 * чтение, и то исключительно ROLE_ADMIN.
 *
 * Retention: по умолчанию 14 дней (см. $expiresAt) — реально удаляются
 * командой app:prune-entity-revisions (не автоматически, БД сама ничего
 * не чистит). Конкретный писатель может передать null явно, чтобы
 * отключить retention для одной записи — она тогда хранится бессрочно.
 */
#[ORM\Entity]
#[ORM\HasLifecycleCallbacks]
#[ApiResource(
    operations: [
        new GetCollection(
            uriTemplate: '/entity-revisions',
            security: "is_granted('ROLE_ADMIN')",
            normalizationContext: ['groups' => [G::ENTITY_REVISIONS]],
        ),
        new Get(
            uriTemplate: '/entity-revisions/{id}',
            requirements: ['id' => '\d+'],
            security: "is_granted('ROLE_ADMIN')",
            normalizationContext: ['groups' => [G::ENTITY_REVISIONS]],
        ),
    ],
    paginationClientItemsPerPage: true,
    paginationEnabled: true,
    paginationItemsPerPage: 25,
    paginationMaximumItemsPerPage: 50,
)]
#[ApiFilter(SearchFilter::class, properties: ['entityType' => 'exact', 'entityId' => 'exact', 'parentId' => 'exact', 'entity' => 'exact', 'action' => 'exact'])]
class EntityRevision
{
    use CreatedAtTrait;

    public const string ACTION_UPDATED = 'updated';
    public const string ACTION_DELETED = 'deleted';

    /** Человекочитаемые подписи для админки (EntityRevisionCrudController). */
    public const array ACTIONS = [
        'Изменено' => self::ACTION_UPDATED,
        'Удалено'  => self::ACTION_DELETED,
    ];

    /**
     * Человекочитаемые подписи entityType для админки. Держим здесь же, где
     * и сами дискриминаторы пишутся листенерами (TicketListener,
     * ChatMessageListener, TechSupportMessageListener, ReviewListener,
     * AbstractApiHelperController::logImagesDeletion) — при добавлении
     * версионирования новой сущности новую строку нужно добавить и сюда.
     */
    public const array ENTITY_TYPES = [
        'Объявление / услуга'    => 'ticket',
        'Сообщение чата'         => 'chat_message',
        'Сообщение техподдержки' => 'tech_support_message',
        'Отзыв'                  => 'review',
        'Фото'                   => 'multiple_image',
    ];

    /**
     * Таблица переводов для $entity — класса сущности, которой владеет
     * $parentId (или, если родителя нет — entityType=ticket, — самого себя).
     * В отличие от ENTITY_TYPES (тип ЭТОЙ записи ревизии: ticket/review/…),
     * $entity — это "куда она относится" на уровень выше: например, у
     * review $entity='Ticket', потому что и review, и его parentId
     * указывают на Ticket; у multiple_image $entity — класс той сущности,
     * которой принадлежало фото (Ticket/Review/ChatMessage/… — то же самое,
     * что раньше лежало в $snapshot['parentType'], теперь отдельной
     * фильтруемой колонкой). Ключ — короткое имя класса (см. setEntity() в
     * каждом писателе), значение — перевод для админки.
     */
    public const array ENTITIES = [
        'Ticket'              => 'Объявления/услуги',
        'Chat'                => 'Чат',
        'ChatMessage'         => 'Сообщение чата',
        'TechSupport'         => 'Техподдержка',
        'TechSupportMessage'  => 'Сообщение техподдержки',
        'Review'              => 'Отзыв',
        'Gallery'             => 'Галерея',
        'Appeal'              => 'Жалоба',
    ];

    /**
     * Переводы имён полей внутри $snapshot для getSnapshotSummary() —
     * "description: было → стало" превращается в "Описание: было → стало".
     * Один общий список на все entityType, потому что имена полей
     * пересекаются (description есть у ticket/chat_message/
     * tech_support_message/review) — таскать перевод за каждым писателем
     * отдельно смысла нет. Поле, которого здесь нет, просто печатается как
     * есть (см. getSnapshotSummary) — новый versioned-писатель ничего не
     * сломает, если забыть сюда что-то добавить, просто будет непереведённая
     * строка вместо ошибки.
     */
    private const array FIELD_LABELS = [
        'title'            => 'Заголовок',
        'description'      => 'Описание',
        'notice'           => 'Доп. описание',
        'budget'           => 'Бюджет',
        'negotiableBudget' => 'Договорная цена',
        'service'          => 'Тип (услуга/объявление)',
        'active'           => 'Активность',
        'priority'         => 'Приоритет',
        'category'         => 'Категория',
        'subcategory'      => 'Подкатегория',
        'unit'             => 'Единицы',
        'rating'           => 'Рейтинг',
    ];

    /** По умолчанию хранится 14 дней — см. $expiresAt и app:prune-entity-revisions. */
    private const string DEFAULT_RETENTION = '+14 days';

    /**
     * По умолчанию — createdAt + 14 дней, проставляется прямо тут, в момент
     * создания объекта (не в PrePersist — иначе нельзя было бы отличить
     * "ещё не решили" от "явно поставили null"). Писатель (листенер) может
     * позвать setExpiresAt(null) ПОСЛЕ создания, чтобы отключить retention
     * для конкретной записи — она тогда хранится бессрочно.
     */
    public function __construct()
    {
        $this->expiresAt = new DateTimeImmutable(self::DEFAULT_RETENTION);
    }

    public function __toString(): string
    {
        return "#{$this->id} EntityRevision ({$this->entityType}#{$this->entityId}, {$this->action})";
    }

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups([G::ENTITY_REVISIONS])]
    private ?int $id = null;

    /** Короткий строковый дискриминатор версионируемой сущности — 'ticket' и т.д. */
    #[ORM\Column(length: 32)]
    #[Groups([G::ENTITY_REVISIONS])]
    private ?string $entityType = null;

    #[ORM\Column]
    #[Groups([G::ENTITY_REVISIONS])]
    private ?int $entityId = null;

    /**
     * ID сущности, которой непосредственно принадлежит entityId, если она
     * есть — прямая родительская связь (FK), а не самая старшая в цепочке.
     * Для entityType=chat_message — ID Chat (ChatMessage.chat), для
     * review — ID Ticket (Review.ticket), для tech_support_message — ID
     * TechSupport (TechSupportMessage.techSupport). Для entityType=ticket
     * родителя нет (Ticket — корень иерархии) — null. Для
     * entityType=multiple_image — ID той сущности, которой принадлежало
     * фото (см. AbstractApiHelperController::logImagesDeletion). Одна
     * запись может описывать сразу НЕСКОЛЬКО удалённых фото за один запрос
     * (см. $snapshot) — parentId у них общий, это ID их владельца, а не
     * какого-то конкретного фото из списка.
     */
    #[ORM\Column(nullable: true)]
    #[Groups([G::ENTITY_REVISIONS])]
    private ?int $parentId = null;

    /**
     * Короткое имя класса сущности, которой принадлежит $parentId (или
     * самого себя — 'Ticket' у entityType=ticket, ему принадлежать
     * нечему). См. ENTITIES для перевода и подробное объяснение разницы с
     * entityType там же.
     */
    #[ORM\Column(length: 32, nullable: true)]
    #[Groups([G::ENTITY_REVISIONS])]
    private ?string $entity = null;

    /** ACTION_UPDATED | ACTION_DELETED */
    #[ORM\Column(length: 16)]
    #[Groups([G::ENTITY_REVISIONS])]
    private ?string $action = null;

    /**
     * При update — предыдущие значения изменённых полей (только то, что
     * реально поменялось, не весь объект). При delete — снимок удалённых
     * данных. Форму снапшота решает писатель (см. TicketListener).
     */
    #[ORM\Column(type: 'json')]
    #[Groups([G::ENTITY_REVISIONS])]
    private array $snapshot = [];

    /** Кто внёс изменение/удаление. */
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    #[ApiProperty(writable: false)]
    #[Groups([G::ENTITY_REVISIONS])]
    private ?User $actor = null;

    /** Опциональная причина — в основном для модераторских удалений. */
    #[ORM\Column(type: 'text', nullable: true)]
    #[Groups([G::ENTITY_REVISIONS])]
    private ?string $reason = null;

    /**
     * Когда запись можно удалить (см. app:prune-entity-revisions).
     * null = retention отключён, хранится бессрочно.
     */
    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    #[Groups([G::ENTITY_REVISIONS])]
    private ?DateTimeImmutable $expiresAt;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getEntityType(): ?string
    {
        return $this->entityType;
    }

    public function setEntityType(string $entityType): static
    {
        $this->entityType = $entityType;
        return $this;
    }

    public function getEntityId(): ?int
    {
        return $this->entityId;
    }

    public function setEntityId(int $entityId): static
    {
        $this->entityId = $entityId;
        return $this;
    }

    public function getParentId(): ?int
    {
        return $this->parentId;
    }

    public function setParentId(?int $parentId): static
    {
        $this->parentId = $parentId;
        return $this;
    }

    public function getEntity(): ?string
    {
        return $this->entity;
    }

    public function setEntity(?string $entity): static
    {
        $this->entity = $entity;
        return $this;
    }

    public function getAction(): ?string
    {
        return $this->action;
    }

    public function setAction(string $action): static
    {
        $this->action = $action;
        return $this;
    }

    public function getSnapshot(): array
    {
        return $this->snapshot;
    }

    public function setSnapshot(array $snapshot): static
    {
        $this->snapshot = $snapshot;
        return $this;
    }

    public function getActor(): ?User
    {
        return $this->actor;
    }

    public function setActor(?User $actor): static
    {
        $this->actor = $actor;
        return $this;
    }

    public function getReason(): ?string
    {
        return $this->reason;
    }

    public function setReason(?string $reason): static
    {
        $this->reason = $reason;
        return $this;
    }

    public function getExpiresAt(): ?DateTimeImmutable
    {
        return $this->expiresAt;
    }

    /** null отключает retention для этой конкретной записи — хранится бессрочно. */
    public function setExpiresAt(?DateTimeImmutable $expiresAt): static
    {
        $this->expiresAt = $expiresAt;
        return $this;
    }

    /**
     * Плоское человекочитаемое представление snapshot — "поле: было → стало"
     * по строке на изменённое поле (для action=deleted пары старое/новое
     * нет, просто "поле: значение"). Виртуальный геттер, НЕ ORM-поле —
     * единственный чистый способ показать это в EasyAdmin: биндить поле
     * формы напрямую на $snapshot (Doctrine json-колонка) нельзя, EasyAdmin
     * определяет тип виджета по маппингу колонки и падает на
     * "Array to string conversion" независимо от класса Field.
     *
     * Строки уже экранированы и разделены реальным "<br>", а не "\n":
     * EntityRevisionCrudController показывает это поле через TextEditorField
     * (Trix), а форма редактирования отдаёт значение геттера Trix-редактору
     * напрямую как HTML-источник — обычный "\n" в HTML визуально
     * схлопывается (как в любом браузере), тогда как "<br>" сохраняется.
     * На детальной странице поле рендерится через шаблон crud/field/text
     * (см. setTemplateName в контроллере), который выводит значение как
     * доверенный raw HTML — повторного экранирования не будет.
     */
    public function getSnapshotSummary(): string
    {
        if (!$this->snapshot) return '—';

        // Пакетное удаление фото (см. AbstractApiHelperController::
        // logImagesDeletion) — один EntityRevision на ВСЕ фото, удалённые
        // за один запрос, а не по записи на каждое. Форма снапшота другая:
        // список путей, а не "поле: было → стало", разбираем отдельно.
        if (isset($this->snapshot['images']) && is_array($this->snapshot['images'])) {
            $lines = [];
            foreach ($this->snapshot['images'] as $entry) {
                $path = is_array($entry) ? ($entry['image'] ?? '') : (string) $entry;
                $lines[] = htmlspecialchars("Фото: {$path}", ENT_QUOTES, 'UTF-8');
            }
            return implode('<br>', $lines);
        }

        $lines = [];
        foreach ($this->snapshot as $field => $value) {
            $label = self::FIELD_LABELS[$field] ?? $field;

            if (is_array($value) && array_key_exists('old', $value) && array_key_exists('new', $value)) {
                $line = "{$label}: " . $this->stringifySnapshotValue($value['old']) . ' → ' . $this->stringifySnapshotValue($value['new']);
            } else {
                $line = "{$label}: " . $this->stringifySnapshotValue($value);
            }
            $lines[] = htmlspecialchars($line, ENT_QUOTES, 'UTF-8');
        }

        return implode('<br>', $lines);
    }

    private function stringifySnapshotValue(mixed $value): string
    {
        if ($value === null)  return '(пусто)';
        if (is_bool($value))  return $value ? 'да' : 'нет';
        if (is_scalar($value)) return (string) $value;

        return json_encode($value, JSON_UNESCAPED_UNICODE);
    }
}
