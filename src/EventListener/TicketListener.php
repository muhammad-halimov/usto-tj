<?php

namespace App\EventListener;

use App\Entity\TechSupport\TicketApproval;
use App\Entity\Ticket\Ticket;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsEntityListener;
use Doctrine\ORM\Event\PostUpdateEventArgs;
use Doctrine\ORM\Event\PreUpdateEventArgs;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Events;

/**
 * При содержательном изменении объявления/услуги заводим новую TicketApproval
 * на этот же Ticket — это переиспользует уже готовый механизм
 * TicketApprovalListener (least-loaded-балансировка администранта +
 * email/Telegram уведомление, см. AdminLoadBalancerService и
 * NotifyNewTicketApprovalEmailService/TelegramBotService), без дублирования
 * кода уведомлений. Админ получает то же самое письмо/сообщение в Telegram
 * со ссылкой на карточку в EasyAdmin, что и при первом создании тикета — и
 * может глазком проверить правки, не заходя специально в саму админку.
 *
 * preUpdate/postUpdate, а не один postUpdate:
 *   Changeset (какие поля реально изменились) доступен только в preUpdate —
 *   в postUpdate Doctrine его уже не отдаёт. Но создавать и сохранять новую
 *   сущность внутри preUpdate нельзя — это ломает текущий flush (ограничение
 *   Doctrine). Поэтому решение "уведомлять или нет" принимается в preUpdate
 *   и запоминается на инстансе, а сама запись TicketApproval создаётся в
 *   postUpdate — так же, как ChatResponseListener::postPersist делает
 *   персист+flush в ответ на событие другой сущности.
 */
#[AsEntityListener(event: Events::preUpdate, entity: Ticket::class)]
#[AsEntityListener(event: Events::postUpdate, entity: Ticket::class)]
class TicketListener
{
    /**
     * Поля, изменение которых считается "содержательным" — тем, что стоит
     * показать админу, и их человекочитаемые подписи для уведомления.
     * Счётчики (viewsCount/responsesCount/reviewsCount, которые тикают на
     * каждый просмотр/отклик) и служебные/вычисляемые поля
     * (approved/banned/updatedAt) намеренно не в списке — иначе уведомление
     * улетало бы на каждый чих, а не только на реальную правку объявления.
     */
    private const array NOTIFIABLE_FIELDS = [
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
    ];

    /** @var array<int, string[]> Тикеты (по spl_object_id) → подписи изменённых полей, для postUpdate. */
    private array $pending = [];

    public function __construct(private readonly EntityManagerInterface $entityManager) {}

    public function preUpdate(Ticket $ticket, PreUpdateEventArgs $event): void
    {
        $changedFields = array_intersect(array_keys($event->getEntityChangeSet()), array_keys(self::NOTIFIABLE_FIELDS));

        if (!$changedFields) return;

        $this->pending[spl_object_id($ticket)] = array_map(
            fn(string $field): string => self::NOTIFIABLE_FIELDS[$field],
            $changedFields,
        );
    }

    public function postUpdate(Ticket $ticket, PostUpdateEventArgs $event): void
    {
        $key = spl_object_id($ticket);

        if (!isset($this->pending[$key])) return;

        $changedLabels = $this->pending[$key];
        unset($this->pending[$key]);

        // Description переиспользует уже существующее поле TicketApproval
        // (то же, что админ заполняет вручную при создании подтверждения) —
        // сюда пишем, что именно изменилось, чтобы уведомление показывало
        // это без захода в админку (см. NotifyNewTicketApprovalEmailService/
        // TelegramBotService — они читают это поле).
        $approval = (new TicketApproval())
            ->setTicket($ticket)
            ->setDescription('Изменены поля: ' . implode(', ', $changedLabels));

        // persist+flush здесь безопасны: postUpdate вызывается уже после
        // записи изменений Ticket в БД, текущий flush завершён.
        $this->entityManager->persist($approval);
        $this->entityManager->flush();
    }
}
