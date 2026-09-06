<?php

namespace App\Controller\Api\CRUD\POST\TechSupport\TechSupport;

use App\ApiResource\AppMessages;
use App\Controller\Api\CRUD\Abstract\AbstractApiHelperController;
use App\Repository\TechSupport\TechSupportMessageRepository;
use App\Repository\TechSupport\TechSupportRepository;
use DateTimeImmutable;
use Symfony\Component\HttpFoundation\JsonResponse;

/**
 * POST /api/tech-supports/{id}/read
 *
 * Аналог ApiPostMarkChatReadController (см. Chat) — помечает все непрочитанные
 * сообщения тикета техподдержки как прочитанные для текущего пользователя.
 * "Непрочитанное" = сообщение написано не самим bearer'ом (author != bearer)
 * и readAt === null.
 *
 * Доступ: автор тикета, назначенный на него администрант, либо ЛЮБОЙ
 * ROLE_ADMIN (тот же паттерн, что у ApiGetTechSupportController — в отличие
 * от /subscribe, который остаётся приватным каналом только автор+администрант).
 * ROLE_SUPER_ADMIN проходит эту же проверку — ему getRoles() всегда
 * дополнительно возвращает 'ROLE_ADMIN' (см. User::getRoles()).
 *
 * Как и у чата, загружаем сущности через Unit of Work (а не bulk DQL UPDATE),
 * чтобы каждое сообщение прошло через постUpdate-хуки Doctrine. Сейчас
 * TechSupportMessageListener слушает только postPersist (по замыслу — см.
 * его докблок), так что live-обновление о "прочитано" по Mercure не летит,
 * но раз чат построен так — держим тот же путь и здесь, чтобы включить это
 * позже одной строкой, без переделки контроллера.
 */
class ApiPostMarkTechSupportReadController extends AbstractApiHelperController
{
    public function __construct(
        private readonly TechSupportRepository        $techSupportRepository,
        private readonly TechSupportMessageRepository  $techSupportMessageRepository,
    ) {}

    public function __invoke(string $id): JsonResponse
    {
        $bearer = $this->checkedUser();

        $techSupport = $this->techSupportRepository->find($id);

        if (!$techSupport)
            return $this->errorJson(AppMessages::TECH_SUPPORT_NOT_FOUND);

        $isAdmin = in_array('ROLE_ADMIN', $bearer->getRoles(), true);

        if (!$isAdmin && $techSupport->getAuthor() !== $bearer && $techSupport->getAdministrant() !== $bearer)
            return $this->errorJson(AppMessages::OWNERSHIP_MISMATCH);

        $unread = $this->techSupportMessageRepository->findUnreadByRecipient($techSupport, $bearer);

        $now = new DateTimeImmutable();

        foreach ($unread as $message) {
            $message->setReadAt($now);
        }

        $this->flush();

        return $this->json(null, 204);
    }
}
