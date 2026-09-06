<?php

namespace App\EventListener;

use App\ApiResource\AppMessages;
use App\Entity\Extra\EntityRevision;
use App\Entity\Geography\Abstract\Address;
use App\Entity\Geography\Abstract\AddressComponent;
use App\Entity\TechSupport\TicketApproval;
use App\Entity\Ticket\Ticket;
use App\Entity\User;
use App\Repository\TechSupport\TicketApprovalRepository;
use DateTimeImmutable;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsEntityListener;
use Doctrine\ORM\Event\OnFlushEventArgs;
use Doctrine\ORM\Event\PostUpdateEventArgs;
use Doctrine\ORM\Event\PreUpdateEventArgs;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Events;
use Doctrine\ORM\Query\Expr\Join;
use Doctrine\ORM\UnitOfWork;
use Symfony\Bundle\SecurityBundle\Security;

/**
 * При содержательном изменении объявления/услуги (title/description/...,
 * см. NOTIFIABLE_FIELDS):
 *   1. Заводим новую TicketApproval на этот же Ticket — переиспользует
 *      готовый механизм TicketApprovalListener (least-loaded-балансировка
 *      администранта + email/Telegram уведомление), без дублирования кода.
 *   2. Сбрасываем Ticket::approved обратно в false — правка требует
 *      повторного одобрения, объявление до этого не должно оставаться
 *      публично видимым как одобренное со старым содержанием.
 *   3. Пишем EntityRevision со снимком "было/стало" по изменившимся полям —
 *      audit trail для споров/Appeal (см. задачу про версионирование).
 *
 * preUpdate/postUpdate, а не один postUpdate:
 *   Changeset (какие поля реально изменились, старое/новое значение)
 *   доступен только в preUpdate — в postUpdate Doctrine его уже не отдаёт.
 *   Но создавать/сохранять новые сущности и трогать ДРУГИЕ поля внутри
 *   preUpdate небезопасно (PreUpdateEventArgs::setNewValue() бросает
 *   исключение на поле, которого изначально не было в changeset — то есть
 *   approved так не сбросить, если правили только title). Поэтому:
 *     - в preUpdate только запоминаем на инстансе старые/новые значения +
 *       человекочитаемые подписи изменившихся полей;
 *     - в postUpdate персистим TicketApproval + EntityRevision и сбрасываем
 *       approved вторым flush'ем — так же, как ChatResponseListener::
 *       postPersist делает персист+flush в ответ на событие другой сущности.
 *
 * Адрес (Ticket::$addresses) отслеживается ОТДЕЛЬНО, через onFlush, а не
 * через NOTIFIABLE_FIELDS/preUpdate/postUpdate выше — и вот почему:
 *   $addresses — ManyToMany-коллекция (join-таблица ticket_address), а не
 *   колонка на самой ticket. PreUpdateEventArgs::getEntityChangeSet() видит
 *   только изменения собственных колонок сущности — коллекции туда в
 *   принципе не попадают (это отдельный уровень отслеживания UnitOfWork).
 *   Более того, если адрес — ЕДИНСТВЕННОЕ, что поменялось (см.
 *   ApiPatchTicketController — там при непустом $dto->address вызывается
 *   getAddresses()->clear() и адреса пересобираются заново), то у самой
 *   Ticket вообще не будет запланирован UPDATE (id/остальные колонки не
 *   менялись), а значит preUpdate/postUpdate для Ticket в принципе не
 *   вызовутся. onFlush — единственное место, где виден сам факт "коллекция
 *   адресов стала другой" (через UnitOfWork::getScheduledCollectionUpdates()),
 *   независимо от того, что конкретно её поменяло — этот же PATCH-контроллер
 *   или, например, редактирование адресов тикета из EasyAdmin
 *   (TicketCrudController::configureFields() — CollectionField 'addresses').
 *
 * ВАЖНО — почему тут ровно ОДИН flush, а не второй (как в postUpdate() для
 * скалярных полей): изначально это тоже было сделано как onFlush+postFlush
 * (по аналогии), и второй flush() внутри postFlush РЕАЛЬНО ЛОМАЛ ДАННЫЕ —
 * см. UnitOfWork::commit(): dispatchPostFlushEvent() вызывается ДО
 * postCommitCleanup(), которая только и обнуляет $collectionDeletions/
 * $collectionUpdates. Значит на момент postFlush эти массивы у ИСХОДНОГО
 * flush ещё не пусты; второй flush(), вызванный изнутри postFlush, стартует
 * СВОЙ commit() и видит эти неочищенные записи как "надо сделать" — и
 * повторно выполняет DELETE FROM ticket_address, стирая только что
 * корректно вставленные строки. Поэтому вся работа с адресом — persist
 * новых TicketApproval/EntityRevision, recomputeSingleEntityChangeSet() для
 * approved — сделана прямо в onFlush, ВНУТРИ текущего flush, без второго
 * вызова flush(). Единственное, что это меняет по сравнению с postUpdate():
 * snapshot 'new' не может ссылаться на id ещё не вставленных Address (они
 * получат id только после INSERT, который случится уже после onFlush) —
 * поэтому snapshot строится не по id самого Address, а по id уже
 * существующих справочников геозоны (province/city/.../village), которые
 * прописаны в Address ДО его собственного INSERT (см. addressSnapshot()).
 *
 * Коалесинг правок в одну TicketApproval (см. resolveApproval()):
 *   Если автор поменял budget, а через 5 минут ещё и description — вместо
 *   двух отдельных заявок на подтверждение админу заводится ОДНА: вторая
 *   правка находит уже существующую неодобренную заявку по этому тикету
 *   (созданную не позже TICKET_APPROVAL_REUSE_WINDOW назад) и ДОБАВЛЯЕТ
 *   изменения к ней (TicketApproval::appendSnapshot() — каждая правка своим
 *   отдельным элементом списка с меткой времени, ничего не сливается и не
 *   теряется: budget 200→300, потом 300→350 — это два разных элемента, а
 *   не смазанное 200→350). Через TICKET_APPROVAL_REUSE_WINDOW окно
 *   "закрывается" — следующая правка заводит новую заявку, а не копит
 *   бесконечно в одной. EntityRevision этому НЕ подчиняется — она всегда
 *   новая запись на каждую правку (неизменяемая история, см. её докблок),
 *   коалесинг касается только
 *   TicketApproval (очереди на подтверждение).
 *   Админ уведомляется в любом случае — что при создании новой заявки
 *   (TicketApprovalListener::postPersist), что при обновлении существующей
 *   (TicketApprovalListener::postUpdate — реагирует именно на изменение
 *   description/snapshot, а не вообще на любой апдейт заявки, иначе админ
 *   получал бы уведомление о своём же действии при простановке approved).
 */
#[AsEntityListener(event: Events::postPersist, entity: Ticket::class)]
#[AsEntityListener(event: Events::preUpdate, entity: Ticket::class)]
#[AsEntityListener(event: Events::postUpdate, entity: Ticket::class)]
#[AsDoctrineListener(event: Events::onFlush)]
class TicketListener
{
    /**
     * Поля, изменение которых считается "содержательным" — тем, что стоит
     * показать админу и что требует повторного одобрения, и их
     * человекочитаемые подписи для уведомления. Счётчики
     * (viewsCount/responsesCount/reviewsCount, которые тикают на каждый
     * просмотр/отклик) и служебные/вычисляемые поля (approved/banned/
     * updatedAt) намеренно не в списке — иначе уведомление и сброс approved
     * срабатывали бы на каждый чих, а не только на реальную правку.
     *
     * 'active' сюда намеренно НЕ входит (хотя раньше входило): это просто
     * тумблер "приостановить/возобновить своё же объявление" — рутинное
     * действие автора/мастера, а не правка содержания. Раньше он был в
     * списке наравне с title/budget/... из-за чего banальный active:false
     * (временно скрыть объявление) уже гонял тикет через ВСЮ админскую
     * машину: новый TicketApproval → least-loaded-админ →
     * Telegram/email-уведомление, плюс approved сбрасывался в false, из-за
     * чего объявление ещё и требовало повторного одобрения только чтобы
     * снова его включить. Реальная правка контента (title/budget/...) по-
     * прежнему проходит весь этот цикл как положено — просто active больше
     * не триггерит его сам по себе.
     */
    private const array NOTIFIABLE_FIELDS = [
        'title'            => 'Заголовок',
        'description'      => 'Описание',
        'notice'           => 'Доп. описание',
        'budget'           => 'Бюджет',
        'negotiableBudget' => 'Договорная цена',
        'service'          => 'Тип (услуга/объявление)',
        'priority'         => 'Приоритет',
        'category'         => 'Категория',
        'subcategory'      => 'Подкатегория',
        'unit'             => 'Единицы',
    ];

    /**
     * 7 уровней геоиерархии адреса (Address::getProvince()/getCity()/...),
     * в порядке от крупного к мелкому. Единственное место, где этот список
     * перечислен явно — addressSnapshot()/geoRefFromRow()/DQL-запрос
     * "старого" состояния адреса в onFlush() все строятся по нему циклом,
     * вместо того чтобы вручную дублировать одни и те же 7 строк в
     * нескольких местах (было именно так раньше — 7 руками выписанных
     * LEFT JOIN на translation, сперва в сыром SQL, потом дословно
     * повторённых в QueryBuilder).
     */
    private const array GEO_LEVELS = [
        'province', 'city', 'suburb', 'district', 'community', 'settlement', 'village',
    ];

    /**
     * @var array<int, array<string, mixed>>
     * Тикеты (по spl_object_id) → снимок изменившихся полей для postUpdate.
     */
    private array $pending = [];

    /**
     * Окно, в пределах которого правки одного тикета копятся в ОДНОЙ и той
     * же неодобренной TicketApproval, а не заводят новую на каждую правку —
     * см. resolveApproval() и докблок класса.
     */
    private const string TICKET_APPROVAL_REUSE_WINDOW = '-24 hours';

    /**
     * @var array<int, true> Тикеты (по spl_object_id), для которых onFlush()
     * уже записал изменение адреса В ЭТОМ HTTP-запросе — защита от повторной
     * обработки при вложенном flush().
     *
     * Почему это вообще возможно: если в одном запросе меняются И скаляр
     * (title/budget/...), И адрес, то postUpdate() (скалярная ветка) делает
     * СВОЙ отдельный flush() — а он вызывается ИЗНУТРИ ещё не завершённого
     * исходного flush() (postUpdate фактически выполняется в середине
     * UnitOfWork::commit() исходного flush, см. её докблок). У этого
     * вложенного flush() СВОЙ onFlush() — и он снова видит ту же коллекцию
     * addresses в getScheduledCollectionUpdates(): Doctrine снимает пометку
     * "грязная" только в фазе обработки collection updates самого
     * UnitOfWork, а она идёт ПОСЛЕ executeUpdates()/postUpdate() (см.
     * UnitOfWork::commit()) — то есть ещё не наступила к моменту вложенного
     * flush(). Без этой защиты один и тот же адрес добавлялся бы в снимок
     * TicketApproval дважды и создавал бы вторую, лишнюю EntityRevision —
     * ровно так и было, поймано живым тестом при разработке (title/notice +
     * address за один PATCH).
     */
    private array $addressChangeHandled = [];

    public function __construct(
        private readonly EntityManagerInterface   $entityManager,
        private readonly Security                 $security,
        private readonly TicketApprovalRepository $ticketApprovalRepository,
    ) {}

    /**
     * Поля, чей getter делает strip_tags() при чтении (Ticket::
     * getDescription()/getNotice()) — у остальных NOTIFIABLE_FIELDS такого
     * нет (например, getTitle() отдаёт $title как есть). Из-за этого
     * Doctrine-changeset может показывать "изменение" там, где видимый
     * пользователю текст на самом деле не поменялся:
     *   - description: пересохранение через Trix-редактор в админке
     *     (TicketCrudController: TextEditorField) оборачивает контент в
     *     <div>, даже если сам текст не трогали — сырое значение меняется,
     *     видимое (после strip_tags) — нет;
     *   - notice: ApiPatchTicketController всегда пере-сетает notice через
     *     ->setNotice($dto->notice ?? $ticket->getNotice()), а getNotice()
     *     на PHP 8.1+ молча коэрсит strip_tags(null) в '' — то есть notice
     *     "меняется" с null на '' при любом PATCH, даже не касающемся его.
     * См. isRealChange() — сравниваем по тому же strip_tags(), что и сам
     * getter, а не по сырому changeSet, иначе такие фантомные правки гоняли
     * бы тикет через весь цикл повторного одобрения (см. докблок класса)
     * без единого реального изменения — ровно так и было, показано вживую.
     */
    private const array STRIP_TAGS_FIELDS = ['description', 'notice'];

    /**
     * БАГФИКС (05.09.2026, по жалобе "не приходит уведомление о новом
     * объявлении"): до этого фикса `TicketApproval` создавалась ЕДИНСТВЕННО
     * внутри preUpdate/postUpdate/onFlush этого же листенера — то есть
     * только когда объявление уже существующее ПРАВЯТ. Сам факт СОЗДАНИЯ
     * нового тикета не порождал ни `TicketApproval`, ни уведомления вообще —
     * `ApiPostTicketController` просто персистит Ticket, и на этом всё:
     * админ узнавал о новом объявлении только если автор его потом хоть
     * как-то редактировал (это и объясняло "правка уведомляет, создание —
     * нет"). Теперь при постоянно КАЖДОМ создании тикета сразу заводится
     * "нулевая" TicketApproval — снимок вида {old: null, new: <значение>}
     * по всем заполненным NOTIFIABLE_FIELDS (пустые поля пропускаем, чтобы
     * не засорять уведомление строками вида "Бюджет: (пусто) → (пусто)").
     * Дальше отрабатывает УЖЕ существующая машина без изменений:
     * TicketApprovalListener::prePersist на TicketApproval назначает
     * наименее загруженного админа, postPersist — шлёт уведомление.
     *
     * persist+flush здесь безопасны по той же причине, что и в
     * postUpdate() ниже: postPersist вызывается уже ПОСЛЕ INSERT самого
     * Ticket, текущий flush завершён — тот же паттерн, что уже
     * использует UserListener::postPersist() для письма подтверждения.
     *
     * Адрес включаем в ТОТ ЖЕ снимок явно (а не полагаемся на отдельную
     * ветку onFlush() ниже) и сразу помечаем $addressChangeHandled —
     * поймано живым тестом: если тикет создаётся сразу с адресом, вложенный
     * flush() из этого метода запускает СВОЙ onFlush(), а тот видит
     * коллекцию addresses как "изменившуюся" — причём проверка
     * "$uow->isScheduledForInsert($ticket)" его на этот раз НЕ спасает
     * (INSERT уже прошёл, тикет больше не в очереди на вставку), из-за
     * чего заводилась ВТОРАЯ,
     * лишняя TicketApproval только с адресом вместо одной цельной.
     */
    public function postPersist(Ticket $ticket): void
    {
        $snapshot = [];
        foreach (self::NOTIFIABLE_FIELDS as $field => $label) {
            $getter = 'get' . ucfirst($field);
            $value  = $ticket->$getter();

            if ($value === null || $value === '') continue;

            $snapshot[$field] = ['old' => null, 'new' => $this->toSnapshotValue($value)];
        }

        $addresses = $ticket->getAddresses();
        if (!$addresses->isEmpty()) {
            $snapshot['address'] = [
                'old' => [],
                'new' => array_values(array_map(
                    fn(Address $address): array => $this->addressSnapshot($address),
                    $addresses->toArray(),
                )),
            ];
        }

        // См. докблок выше — гасит повторную обработку того же адреса в
        // onFlush() ниже, если flush() чуть дальше спровоцирует его вложенный вызов.
        $this->addressChangeHandled[spl_object_id($ticket)] = true;

        // Совсем без полей (теоретически — все NOTIFIABLE_FIELDS пусты и
        // адреса нет) заводить нечего показывать админу.
        if (!$snapshot) return;

        $approval = (new TicketApproval())
            ->setTicket($ticket)
            // true — это создание, а не правка, см. TicketApproval::appendSnapshot()/
            // isCreationOnly() (05.09.2026, по жалобе на уведомление-"диф" для новых тикетов).
            ->appendSnapshot($snapshot, true)
            ->refreshDescriptionFromSnapshot();

        $this->entityManager->persist($approval);
        $this->entityManager->flush();
    }

    public function preUpdate(Ticket $ticket, PreUpdateEventArgs $event): void
    {
        $changeSet     = $event->getEntityChangeSet();
        $changedFields = array_intersect(array_keys($changeSet), array_keys(self::NOTIFIABLE_FIELDS));
        $changedFields = array_filter(
            $changedFields,
            fn(string $field): bool => $this->isRealChange($field, $changeSet[$field]),
        );

        if (!$changedFields) return;

        $snapshot = [];
        foreach ($changedFields as $field) {
            // changeSet[$field] = [старое, новое] — сохраняем оба, приводим
            // объекты (category/subcategory/unit) к JSON-безопасному виду.
            $snapshot[$field] = [
                'old' => $this->toSnapshotValue($changeSet[$field][0]),
                'new' => $this->toSnapshotValue($changeSet[$field][1]),
            ];
        }

        $this->pending[spl_object_id($ticket)] = $snapshot;
    }

    /**
     * @param array{0: mixed, 1: mixed} $diff changeSet[$field] — [старое, новое]
     */
    private function isRealChange(string $field, array $diff): bool
    {
        if (!in_array($field, self::STRIP_TAGS_FIELDS, true)) return true;

        [$old, $new] = $diff;

        return trim(strip_tags((string) $old)) !== trim(strip_tags((string) $new));
    }

    public function postUpdate(Ticket $ticket, PostUpdateEventArgs $event): void
    {
        $key = spl_object_id($ticket);

        if (!isset($this->pending[$key])) return;

        $snapshot = $this->pending[$key];
        unset($this->pending[$key]);

        [$approval, $revision] = $this->buildApprovalAndRevision($ticket, $snapshot);

        // Правка содержимого требует повторного одобрения — тикет не
        // должен оставаться публично видимым как одобренный со старым
        // содержанием, пока новую версию не пересмотрит админ.
        if ($ticket->getApproved()) {
            $ticket->setApproved(false);
        }

        // persist+flush здесь безопасны: postUpdate вызывается уже после
        // записи изменений Ticket в БД, текущий flush завершён.
        $this->entityManager->persist($approval);
        $this->entityManager->persist($revision);
        $this->entityManager->flush();
    }

    /**
     * Ловим сам факт "коллекция addresses у какого-то Ticket стала другой"
     * через UnitOfWork::getScheduledCollectionUpdates() — единственное
     * место, где такое вообще видно (см. докблок класса). Вся обработка —
     * прямо здесь, в один flush (см. докблок класса — почему НЕ postFlush
     * с повторным flush()).
     *
     * "Старый" список читаем сырым SQL по ticket_address ДО того, как этот
     * flush что-либо там поменяет (onFlush срабатывает до выполнения SQL) —
     * не зависит от того, что уже сделал с PHP-коллекцией $ticket->
     * getAddresses()->clear() (он мог уже сбросить внутренний snapshot
     * коллекции — см. PersistentCollection::clear()). "Новый" список берём
     * прямо из текущего состояния коллекции в памяти — она уже содержит
     * финальный набор Address (просто ещё не сохранённый в БД).
     */
    public function onFlush(OnFlushEventArgs $event): void
    {
        $uow = $this->entityManager->getUnitOfWork();

        foreach ($uow->getScheduledCollectionUpdates() as $collection) {
            $ticket = $collection->getOwner();

            if (!$ticket instanceof Ticket) continue;
            if ($collection->getMapping()->fieldName !== 'addresses') continue;

            // Уже обработали этот тикет в рамках текущего запроса (см.
            // докблок $addressChangeHandled) — повторный (вложенный) вызов
            // flush() снова видит ту же "грязную" коллекцию, но это не
            // новая правка, а эхо уже обработанной.
            if (isset($this->addressChangeHandled[spl_object_id($ticket)])) continue;

            // БАГФИКС (06.09.2026, переход на UUID-PK): было "id === null"
            // — тикет ещё не существовал в БД до этого flush (создаётся
            // прямо сейчас, ApiPostTicketController). При автоинкрементных
            // int-ID это работало, потому что id назначался только ПОСЛЕ
            // реального INSERT (post-insert генератор). У UUID
            // (CustomIdGenerator/UuidGenerator) генератор pre-insert — id
            // назначается сразу при persist(), то есть ЕЩЁ ДО onFlush()
            // даже у только что созданного тикета: "getId() === null"
            // после перехода на UUID был бы всегда false, и этот guard
            // перестал бы отличать новый тикет от существующего вообще —
            // адрес нового тикета снова пошёл бы через ветку "правка",
            // как уже чинили раньше через $addressChangeHandled (см. выше)
            // — только по другой причине. isScheduledForInsert() — правильная,
            // не зависящая от стратегии генерации ID проверка "этот тикет
            // будет вставлен ВПЕРВЫЕ в текущем flush".
            if ($uow->isScheduledForInsert($ticket)) continue;

            // БАГФИКС (05.09.2026, по жалобе "Адрес: (пусто) → X" на КАЖДОЙ
            // правке + "изменения категории вообще не ловятся"): отмечаем
            // ДО запроса "старого" состояния и ДО сравнения — а не только
            // когда реально нашли отличие, как было раньше. Причина —
            // тонкость реентерабельности: если та же правка одним запросом
            // меняет и адрес, и скалярное поле (category/title/...),
            // postUpdate() (скалярная ветка) ниже делает СВОЙ отдельный
            // persist()+flush() — а он вызывается ИЗНУТРИ ещё не
            // завершённого текущего flush (постУпдейт срабатывает во время
            // executeUpdates(), см. её докблок). Живым тестом поймано: уже
            // К ЭТОМУ моменту (до postUpdate, сразу после executeInserts()
            // текущего flush) реальный DELETE/INSERT в ticket_address для
            // ЭТОЙ правки УЖЕ отработал — то есть у вложенного flush'а СВОЙ
            // onFlush() видит здесь ту же "грязную" коллекцию ВТОРОЙ раз, но
            // запрос "старого" состояния к этому моменту находит уже НОВУЮ
            // (только что записанную) БД-строку, а не действительно старую
            // — из-за чего сравнение ложно решает "адрес изменился с пустого
            // на текущий", хотя на самом деле адрес мог вообще не меняться
            // (просто фронт прислал его заново вместе с правкой другого
            // поля). Раз отметка ставится СРАЗУ, а не по результату
            // сравнения — первый (внешний, с ещё точно корректным "было")
            // проход успевает либо корректно завершить обработку, либо
            // корректно решить "не изменилось", и ВТОРОЙ (вложенный) проход
            // для этого же $ticket в рамках того же запроса гарантированно
            // пропускается целиком, не долетая до испорченного сравнения.
            $this->addressChangeHandled[spl_object_id($ticket)] = true;

            // DQL/QueryBuilder, а не сырой SQL (как было раньше) — те же 7
            // JOIN'ов на translation, но через ассоциации Doctrine
            // (a.province.translations и т.д.), а не через названия таблиц/
            // колонок руками. join('a.tickets', ...) — Address::$tickets
            // это inverse-сторона ManyToMany (mappedBy: 'addresses' в
            // Ticket) — для JOIN в DQL неважно, какая сторона владеющая,
            // ассоциация промаплена в обе стороны и путь валиден. Не через
            // AddressComponent::$title (общее поле из TitleTrait, лежит в
            // базовой таблице address_component при JOINED-наследовании) —
            // оно у геосправочников пустое, реальное название только в
            // Translation (см. Province/City/... — заполняются через
            // addTranslation() в фикстурах/админке, а не через setTitle()
            // напрямую). IDENTITY(a.{level}) — id связанного справочника без
            // необходимости самого его join'ить ради id, join нужен только
            // чтобы дотянуться до его translations.
            //
            // Локаль — AppMessages::getLocale() (то же ?locale= текущего
            // запроса, что и у сообщений об ошибках, см.
            // AppErrorLocaleListener), а НЕ захардкоженная 'ru' (как было
            // раньше) — снимок адреса иначе всегда писался бы по-русски,
            // даже если тикет создан/изменён с ?locale=tj или ?locale=eng.
            // Тот же источник локали переиспользован ниже в geoRef().
            //
            // Строим SELECT/JOIN циклом по GEO_LEVELS, а не вручную (было —
            // 7 одинаковых по форме строк подряд, менять/читать было тяжело
            // и легко было ошибиться в одной из 7 копий при правке).
            $qb = $this->entityManager->createQueryBuilder()
                ->from(Address::class, 'a')
                ->innerJoin('a.tickets', 'tk', Join::WITH, 'tk.id = :ticketId')
                ->setParameter('ticketId', $ticket->getId())
                ->setParameter('locale', AppMessages::getLocale());

            foreach (self::GEO_LEVELS as $i => $level) {
                $componentAlias = "c{$i}";
                $translationAlias = "tr{$i}";

                $qb
                    ->addSelect("IDENTITY(a.{$level}) AS {$level}_id")
                    ->addSelect("{$translationAlias}.title AS {$level}_title")
                    ->leftJoin("a.{$level}", $componentAlias)
                    ->leftJoin("{$componentAlias}.translations", $translationAlias, Join::WITH, "{$translationAlias}.locale = :locale");
            }

            $oldRows = $qb->getQuery()->getScalarResult();

            // array_values() на обеих сторонах — не косметика: array_map()
            // сохраняет исходные ключи, а $collection->toArray() у Doctrine-
            // коллекции их не гарантирует последовательными от 0 (после
            // add()/remove() в рамках жизни коллекции ключи могут стать
            // дырявыми, напр. [1 => Address]). json_encode() такой массив
            // сериализует как JSON-ОБЪЕКТ ({"1": {...}}), а не массив
            // ([{...}]) — из-за чего "new" в снимке уже реально приходил
            // объектом вместо списка (поймано на живых данных), что ломает
            // и sortedSnapshotKeys() (сравнение по индексам вместо
            // значений), и рендер в SnapshotSummaryTrait (array_is_list()
            // перестаёт узнавать список адресов).
            $oldSnapshots = array_values(array_map(
                fn(array $row): array => [
                    'province'   => $this->geoRefFromRow($row, 'province'),
                    'city'       => $this->geoRefFromRow($row, 'city'),
                    'suburb'     => $this->geoRefFromRow($row, 'suburb'),
                    'district'   => $this->geoRefFromRow($row, 'district'),
                    'community'  => $this->geoRefFromRow($row, 'community'),
                    'settlement' => $this->geoRefFromRow($row, 'settlement'),
                    'village'    => $this->geoRefFromRow($row, 'village'),
                ],
                $oldRows,
            ));

            $newSnapshots = array_values(array_map(
                fn(Address $address): array => $this->addressSnapshot($address),
                $collection->toArray(),
            ));

            // Порядок элементов в коллекции/выборке ничего не значит для
            // "поменялся ли набор адресов" — сравниваем множества, а не
            // последовательности, поэтому обе стороны сортируем по одному
            // и тому же каноническому строковому представлению.
            $oldKeys = $this->sortedSnapshotKeys($oldSnapshots);
            $newKeys = $this->sortedSnapshotKeys($newSnapshots);

            // Отметка уже стоит (см. выше, сразу после проверки getId() ===
            // null) — здесь просто ничего не делаем, если множества и правда
            // совпали.
            if ($oldKeys === $newKeys) continue;

            [$approval, $revision] = $this->buildApprovalAndRevision(
                $ticket,
                ['address' => ['old' => $oldSnapshots, 'new' => $newSnapshots]],
            );

            // Новая или переиспользованная TicketApproval (см.
            // resolveApproval()) — scheduleForCurrentFlush() сама решает,
            // нужен ли persist()+computeChangeSet() (новая) или
            // recomputeSingleEntityChangeSet() (уже управляемая) — см. её
            // докблок и общий докблок класса про второй flush().
            // EntityRevision — всегда новая.
            $this->scheduleForCurrentFlush($approval, $uow);
            $this->scheduleForCurrentFlush($revision, $uow);

            // Правка адреса требует повторного одобрения — та же логика,
            // что и в postUpdate() для скалярных полей, но тут approved
            // меняется уже ПОСЛЕ того, как Doctrine посчитала changeset для
            // Ticket (computeChangeSets() отрабатывает до onFlush) —
            // recomputeSingleEntityChangeSet() пересчитывает его заново,
            // чтобы новое значение approved попало в SQL текущего flush.
            if ($ticket->getApproved()) {
                $ticket->setApproved(false);
                $uow->recomputeSingleEntityChangeSet($this->entityManager->getClassMetadata(Ticket::class), $ticket);
            }
        }
    }

    /**
     * Геосостав адреса — id И название (title) уже существующих справочников
     * (Province/City/.../Village), а не id самого Address: на момент onFlush
     * у ещё не вставленных Address id нет (см. докблок класса), а у ссылок
     * на справочники — есть (они не создаются заново при каждой правке).
     * Название добавлено, чтобы snapshot в EntityRevision был читаем сам по
     * себе — без похода в /api/provinces/{id} и т.д. при разборе спора.
     *
     * @return array<string, ?array{id: string, title: ?string}>
     */
    private function addressSnapshot(Address $address): array
    {
        return [
            'province'   => $this->geoRef($address->getProvince()),
            'city'       => $this->geoRef($address->getCity()),
            'suburb'     => $this->geoRef($address->getSuburb()),
            'district'   => $this->geoRef($address->getDistrict()),
            'community'  => $this->geoRef($address->getCommunity()),
            'settlement' => $this->geoRef($address->getSettlement()),
            'village'    => $this->geoRef($address->getVillage()),
        ];
    }

    /**
     * title — из Translation (локаль текущего запроса, AppMessages::
     * getLocale()), не AddressComponent::getTitle(): у геосправочников это
     * общее поле из TitleTrait пустое, реальное название заполняется только
     * через переводы (см. onFlush() — тот же источник локали и то же
     * обоснование, что и там).
     *
     * @return ?array{id: string, title: ?string}
     */
    private function geoRef(?AddressComponent $component): ?array
    {
        if ($component === null) return null;

        $locale = AppMessages::getLocale();
        $title  = null;
        foreach ($component->getTranslations() as $translation) {
            if ($translation->getLocale() === $locale) {
                $title = $translation->getTitle();
                break;
            }
        }

        return ['id' => $component->getId(), 'title' => $title ?? $component->getTitle()];
    }

    /**
     * То же самое {id, title}, что и geoRef(), но из скалярной строки
     * QueryBuilder::getScalarResult() (см. onFlush) — там нет объектов
     * AddressComponent, только "{level}_id"/"{level}_title" алиасы из
     * IDENTITY(a.{level})/{level}.translations.title.
     *
     * @param array<string, mixed> $row
     * @return ?array{id: string, title: ?string}
     */
    private function geoRefFromRow(array $row, string $level): ?array
    {
        $id = $row["{$level}_id"] ?? null;

        if ($id === null) return null;

        // (int) убран (06.09.2026, переход на UUID-PK) — id геосправочника
        // (province/city/...) теперь UUID-строка, приведение к int
        // обнулило бы её.
        return ['id' => $id, 'title' => $row["{$level}_title"] ?? null];
    }

    /**
     * @param array<int, array<string, ?array{id: string, title: ?string}>> $snapshots
     * @return string[] отсортированный список канонических строк — для
     *         сравнения "набор адресов тот же/другой" независимо от порядка.
     *         Сравниваем только по id (title — чисто для читаемости снимка,
     *         сам по себе он не может измениться независимо от id).
     */
    private function sortedSnapshotKeys(array $snapshots): array
    {
        $keys = array_map(
            fn(array $snapshot): string => implode(':', array_map(
                fn(?array $ref): string => $ref === null ? '' : (string) $ref['id'],
                $snapshot,
            )),
            $snapshots,
        );

        sort($keys);

        return $keys;
    }

    /**
     * Собирает пару TicketApproval + EntityRevision по одному и тому же
     * шаблону — переиспользуется и postUpdate() (скалярные поля), и
     * onFlush() (адрес): в обоих случаях это "содержательная правка,
     * нужно повторное одобрение + запись в audit trail", разница только в
     * том, ЧТО именно изменилось и как это обнаружено.
     *
     * TicketApproval — либо новая, либо переиспользованная (см.
     * resolveApproval()/appendSnapshot() — список отдельных правок за окно
     * TICKET_APPROVAL_REUSE_WINDOW, ничего не сливается), EntityRevision —
     * ВСЕГДА новая запись, коалесинг её не касается (неизменяемая история,
     * одна запись на каждую правку).
     *
     * Человекочитаемые подписи полей (для description/summary) берутся из
     * EntityRevision::FIELD_LABELS по ключам самого $snapshot — отдельным
     * параметром их передавать не нужно (раньше передавались, но с переходом
     * на TicketApproval::refreshDescriptionFromSnapshot() это стало мёртвым
     * параметром — убран).
     *
     * @param array<string, mixed> $snapshot JSON-безопасный снимок "было/стало" ЭТОЙ правки
     * @return array{0: TicketApproval, 1: EntityRevision}
     */
    private function buildApprovalAndRevision(Ticket $ticket, array $snapshot): array
    {
        $approval = $this->resolveApproval($ticket)
            ->appendSnapshot($snapshot)
            ->refreshDescriptionFromSnapshot();

        $revision = (new EntityRevision())
            ->setEntityType('ticket')
            ->setEntityId($ticket->getId())
            // parentId не проставляем — Ticket корень иерархии, родителя
            // у него нет (см. EntityRevision::$parentId). entity указывает
            // сам на себя по той же причине (см. EntityRevision::$entity).
            ->setEntity('Ticket')
            ->setAction(EntityRevision::ACTION_UPDATED)
            ->setSnapshot($snapshot)
            ->setActor($this->currentUser());

        return [$approval, $revision];
    }

    /**
     * Заявка на подтверждение для этой правки — новая или переиспользованная
     * недавняя неодобренная (см. TICKET_APPROVAL_REUSE_WINDOW и докблок
     * класса). description пересобирается из актуального $snapshot целиком
     * в refreshDescriptionFromSnapshot(), уже после appendSnapshot() в
     * вызывающем коде — так он корректно учитывает поля, изменившиеся
     * РАНЬШЕ в этом же окне, а не только текущей правкой.
     */
    private function resolveApproval(Ticket $ticket): TicketApproval
    {
        $reusable = $this->ticketApprovalRepository->findReusableForTicket(
            $ticket,
            new DateTimeImmutable(self::TICKET_APPROVAL_REUSE_WINDOW),
        );

        return $reusable ?? (new TicketApproval())->setTicket($ticket);
    }

    /**
     * Планирует $entity на запись в ТЕКУЩЕМ flush — нужен только внутри
     * onFlush() (адресная ветка): персистить новую сущность в onFlush мало
     * (computeChangeSets() уже отработал раньше, см. докблок класса), а
     * пересчитывать changeset уже управляемой (переиспользованной) сущности
     * после ручной правки её полей — отдельный вызов
     * (recomputeSingleEntityChangeSet). postUpdate() (скалярная ветка) в
     * этом не нуждается вовсе — там обычный, отдельный flush(), где Doctrine
     * сама справляется что для новых, что для уже управляемых сущностей.
     */
    private function scheduleForCurrentFlush(object $entity, UnitOfWork $uow): void
    {
        $classMetadata = $this->entityManager->getClassMetadata($entity::class);

        if ($entity->getId() === null) {
            $this->entityManager->persist($entity);
            $uow->computeChangeSet($classMetadata, $entity);
        } else {
            $uow->recomputeSingleEntityChangeSet($classMetadata, $entity);
        }
    }

    /**
     * Приводит значение поля к JSON-безопасному виду для snapshot.
     * Скаляры (string/bool/int/float/null) — как есть. Объекты-связи
     * (Category/Occupation/Unit) — {id, title} — тот же вид ссылки, что
     * geoRef()/geoRefFromRow() уже используют для адресных справочников
     * (см. SnapshotSummaryTrait::stringifySnapshotValue — уже умеет
     * рендерить именно эту форму как читаемый title, а не голый id).
     * В отличие от геосправочников (Province/City/...), у Category/
     * Occupation/Unit title — обычное поле TitleTrait, заполняется
     * напрямую (setTitle() в фикстурах/админке), Translation тут не нужен.
     */
    private function toSnapshotValue(mixed $value): mixed
    {
        if (is_object($value) && method_exists($value, 'getId')) {
            $ref = ['id' => $value->getId()];

            if (method_exists($value, 'getTitle')) {
                $ref['title'] = $value->getTitle();
            }

            return $ref;
        }

        return $value;
    }

    private function currentUser(): ?User
    {
        /** @var User|null $user */
        $user = $this->security->getUser();

        return $user;
    }
}
