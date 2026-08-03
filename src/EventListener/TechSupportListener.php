<?php

namespace App\EventListener;

use App\Entity\TechSupport\TechSupport;
use App\Service\Extra\AdminLoadBalancerService;
use App\Service\Notification\Email\NotifyNewTechSupportEmailService;
use App\Service\Notification\Telegram\NotifyNewTechSupportTelegramBotService;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsEntityListener;
use Doctrine\ORM\Events;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;

#[AsEntityListener(event: Events::prePersist, entity: TechSupport::class)]
#[AsEntityListener(event: Events::postPersist, entity: TechSupport::class)]
readonly class TechSupportListener
{
    public function __construct(
        private NotifyNewTechSupportTelegramBotService $telegramNotifier,
        private NotifyNewTechSupportEmailService       $emailNotifier,
        private AdminLoadBalancerService               $adminLoadBalancerService,
    ){}

    /**
     * До создания ТП задаем наименее загруженного админа
     */
    public function prePersist(TechSupport $techSupport): void
    {
        if ($techSupport->getStatus() === null) $techSupport->setStatus('new');

        $this->adminLoadBalancerService->setLeastLoadedAdmin($techSupport, ['status' => ['new', 'renewed', 'in_progress']]);
    }

    /**
     * @throws TransportExceptionInterface
     */
    public function postPersist(TechSupport $techSupport): void
    {
        $admin = $techSupport->getAdministrant();
        if ($admin === null || !in_array('ROLE_ADMIN', $admin->getRoles())) return;

        $this->telegramNotifier->sendTechSupportNotification(user: $admin, techSupport: $techSupport);
        $this->emailNotifier->sendTechSupportNotification(user: $admin, techSupport: $techSupport);
    }
}
