<?php

namespace App\EventListener;

use App\Entity\TechSupport\TicketApproval;
use App\Service\Extra\AdminLoadBalancerService;
use App\Service\Notification\Email\NotifyNewTicketApprovalEmailService;
use App\Service\Notification\NotificationDispatcher;
use App\Service\Notification\Telegram\NotifyNewTicketApprovalTelegramBotService;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsEntityListener;
use Doctrine\ORM\Event\PreUpdateEventArgs;
use Doctrine\ORM\Events;

/**
 * Обрабатывает бизнес-логику тикетов техподдержки:
 *
 *  prePersist  — назначает наименее загруженного администратора
 *                (до записи в БД)
 *  postPersist — отправляет уведомление назначенному администратору
 *                (после успешной записи, когда у тикета есть ID) —
 *                срабатывает при создании НОВОЙ заявки.
 *  preUpdate / postUpdate — то же уведомление, но при ПЕРЕИСПОЛЬЗОВАНИИ уже
 *                существующей заявки (см. TicketListener::resolveApproval —
 *                правки одного тикета в пределах 24ч копятся в одной и той
 *                же TicketApproval, а не заводят новую на каждый чих).
 *                Админ должен узнать о новых изменениях в любом случае, даже
 *                если формально это "обновление", а не "создание" записи.
 *
 * Балансировка нагрузки:
 *   При создании каждого нового тикета мы ищем администратора с наименьшим
 *   числом неодобренных заявок (approved = false).
 *   Это простой алгоритм round-robin по нагрузке без внешних очередей.
 *
 * Уведомления: сначала Telegram, email — только если Telegram не прошёл
 * (см. NotificationDispatcher — общая логика для всех notify-листенеров).
 */
#[AsEntityListener(event: Events::prePersist, entity: TicketApproval::class)]
#[AsEntityListener(event: Events::postPersist, entity: TicketApproval::class)]
#[AsEntityListener(event: Events::preUpdate, entity: TicketApproval::class)]
#[AsEntityListener(event: Events::postUpdate, entity: TicketApproval::class)]
class TicketApprovalListener
{
    /**
     * @var array<int, true> Заявки (по spl_object_id), у которых в этом
     * update реально поменялось содержимое (description/snapshot) — их
     * нужно повторно отправить админу в postUpdate. Заявки, у которых
     * поменялось что-то ДРУГОЕ (approved/administrant — собственные
     * действия админа), сюда не попадают — не уведомлять админа о его же
     * действии.
     */
    private array $pendingNotify = [];

    public function __construct(
        private readonly AdminLoadBalancerService                  $adminLoadBalancerService,
        private readonly NotifyNewTicketApprovalEmailService       $emailNotifier,
        private readonly NotifyNewTicketApprovalTelegramBotService $telegramNotifier,
        private readonly NotificationDispatcher                    $dispatcher,
    ){}

    /**
     * До создания объявления/услуги задаем наименее загруженного админа
     */
    public function prePersist(TicketApproval $ticketApproval): void
    {
        $this->adminLoadBalancerService->setLeastLoadedAdmin($ticketApproval, ['approved' => false]);
    }

    /**
     * После создания объявления/услуги отправляем уведомление админу.
     */
    public function postPersist(TicketApproval $ticketApproval): void
    {
        $this->notifyAdmin($ticketApproval);
    }

    /**
     * Ловим, что именно поменялось в переиспользуемой заявке — description/
     * snapshot (содержательная правка тикета, см. TicketListener::
     * resolveApproval/mergeSnapshot) или approved/administrant (действие
     * самого админа в EasyAdmin — за него уведомлять не надо).
     */
    public function preUpdate(TicketApproval $ticketApproval, PreUpdateEventArgs $event): void
    {
        if ($event->hasChangedField('description') || $event->hasChangedField('snapshot')) {
            $this->pendingNotify[spl_object_id($ticketApproval)] = true;
        }
    }

    public function postUpdate(TicketApproval $ticketApproval): void
    {
        $key = spl_object_id($ticketApproval);
        if (!isset($this->pendingNotify[$key])) return;
        unset($this->pendingNotify[$key]);

        $this->notifyAdmin($ticketApproval);
    }

    private function notifyAdmin(TicketApproval $ticketApproval): void
    {
        $admin = $ticketApproval->getAdministrant();
        if ($admin === null || !in_array('ROLE_ADMIN', $admin->getRoles())) return;

        $this->dispatcher->send(
            sendTelegram: fn() => $this->telegramNotifier->sendTicketApprovalNotification($admin, $ticketApproval),
            sendEmail:    fn() => $this->emailNotifier->sendTicketApprovalNotification($admin, $ticketApproval),
            label:        'TicketApproval',
            logContext:   ['ticketApprovalId' => $ticketApproval->getId(), 'adminId' => $admin->getId()],
        );
    }
}
