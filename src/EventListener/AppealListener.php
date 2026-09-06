<?php

namespace App\EventListener;

use App\Entity\Appeal\Appeal\Appeal;
use App\Entity\Appeal\Types\AppealChat;
use App\Entity\Appeal\Types\AppealReview;
use App\Entity\Appeal\Types\AppealTicket;
use App\Entity\Appeal\Types\AppealUser;
use App\Repository\User\UserRepository;
use App\Service\Notification\Email\NotifyNewAppealEmailService;
use App\Service\Notification\NotificationDispatcher;
use App\Service\Notification\Telegram\NotifyNewAppealTelegramBotService;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsEntityListener;
use Doctrine\ORM\Events;

/**
 * БАГФИКС (07.09.2026, по просьбе "прикрути уведомления и для создания
 * жалоб") — этого листенера не существовало вообще: жалоба создавалась
 * (ApiPostAppealConntroller) и просто лежала в БД, ни один админ о ней не
 * узнавал, пока сам не зайдёт в EasyAdmin проверить список.
 *
 * В отличие от TechSupportListener/TicketApprovalListener — тут нет
 * "ответственного админа" (нет поля Appeal::$administrant, нет
 * AdminLoadBalancerService) — уведомление уходит СРАЗУ ВСЕМ админам
 * (ROLE_ADMIN/ROLE_SUPER_ADMIN), не одному по очереди. Жалобы не настолько
 * часты, чтобы их требовалось распределять — сознательный выбор при
 * реализации этой задачи, не забыли добавить, а решили не усложнять.
 *
 * БАГФИКС: изначально была одна аттрибута — #[AsEntityListener(entity:
 * Appeal::class)] — в расчёте на то, что Doctrine распространит listener
 * вниз по JOINED-иерархии на все 4 подтипа. Живой тест это опроверг: при
 * реальном создании AppealUser через API postPersist ни разу не
 * выполнился (ни одного SELECT по таблице user в логе) — Doctrine
 * ClassMetadata у КАЖДОГО конкретного подкласса (AppealChat/AppealReview/
 * AppealTicket/AppealUser) своя, entity listener на родителе на них не
 * распространяется автоматически. Отсюда — 4 отдельные аттрибуты, по
 * одной на каждый реально персистящийся подтип (сам Appeal — abstract,
 * никогда не инстанцируется напрямую, поэтому листенер на нём самом
 * бессмысленен и не нужен).
 */
#[AsEntityListener(event: Events::postPersist, entity: AppealChat::class)]
#[AsEntityListener(event: Events::postPersist, entity: AppealReview::class)]
#[AsEntityListener(event: Events::postPersist, entity: AppealTicket::class)]
#[AsEntityListener(event: Events::postPersist, entity: AppealUser::class)]
class AppealListener
{
    public function __construct(
        private readonly UserRepository                    $userRepository,
        private readonly NotifyNewAppealTelegramBotService  $telegramNotifier,
        private readonly NotifyNewAppealEmailService        $emailNotifier,
        private readonly NotificationDispatcher             $dispatcher,
    ) {}

    public function postPersist(Appeal $appeal): void
    {
        foreach ($this->userRepository->findAllAdmins() as $admin) {
            $this->dispatcher->send(
                sendTelegram: fn() => $this->telegramNotifier->sendAppealNotification($admin, $appeal),
                sendEmail:    fn() => $this->emailNotifier->sendAppealNotification($admin, $appeal),
                label:        'Appeal',
                logContext:   ['appealId' => (string) $appeal->getId(), 'adminId' => (string) $admin->getId()],
            );
        }
    }
}
