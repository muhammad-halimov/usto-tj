<?php

namespace App\EventListener;

use App\Entity\Extra\EntityRevision;
use App\Entity\TechSupport\TicketApproval;
use App\Entity\Ticket\Ticket;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsEntityListener;
use Doctrine\ORM\Event\PostUpdateEventArgs;
use Doctrine\ORM\Event\PreUpdateEventArgs;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Events;
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
 */
#[AsEntityListener(event: Events::preUpdate, entity: Ticket::class)]
#[AsEntityListener(event: Events::postUpdate, entity: Ticket::class)]
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
     * @var array<int, array{labels: string[], snapshot: array<string, mixed>}>
     * Тикеты (по spl_object_id) → данные для postUpdate.
     */
    private array $pending = [];

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly Security               $security,
    ) {}

    public function preUpdate(Ticket $ticket, PreUpdateEventArgs $event): void
    {
        $changeSet     = $event->getEntityChangeSet();
        $changedFields = array_intersect(array_keys($changeSet), array_keys(self::NOTIFIABLE_FIELDS));

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

        $this->pending[spl_object_id($ticket)] = [
            'labels'   => array_map(fn(string $field): string => self::NOTIFIABLE_FIELDS[$field], $changedFields),
            'snapshot' => $snapshot,
        ];
    }

    public function postUpdate(Ticket $ticket, PostUpdateEventArgs $event): void
    {
        $key = spl_object_id($ticket);

        if (!isset($this->pending[$key])) return;

        ['labels' => $changedLabels, 'snapshot' => $snapshot] = $this->pending[$key];
        unset($this->pending[$key]);

        // Description переиспользует уже существующее поле TicketApproval
        // (то же, что админ заполняет вручную при создании подтверждения) —
        // сюда пишем, что именно изменилось, чтобы уведомление показывало
        // это без захода в админку (см. NotifyNewTicketApprovalEmailService/
        // TelegramBotService — они читают это поле).
        $approval = (new TicketApproval())
            ->setTicket($ticket)
            ->setDescription('Изменены поля: ' . implode(', ', $changedLabels));

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
     * Приводит значение поля к JSON-безопасному виду для snapshot.
     * Скаляры (string/bool/int/float/null) — как есть. Объекты-связи
     * (Category/Occupation/Unit) — их id, этого достаточно, чтобы при
     * разборе спора понять, что именно было раньше (сущность-справочник
     * никуда не денется, можно посмотреть по id).
     */
    private function toSnapshotValue(mixed $value): mixed
    {
        if (is_object($value) && method_exists($value, 'getId')) {
            return $value->getId();
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
