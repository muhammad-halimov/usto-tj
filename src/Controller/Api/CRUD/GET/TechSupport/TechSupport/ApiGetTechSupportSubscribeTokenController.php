<?php

namespace App\Controller\Api\CRUD\GET\TechSupport\TechSupport;

use App\ApiResource\AppMessages;
use App\Controller\Api\CRUD\Abstract\AbstractApiHelperController;
use App\Entity\TechSupport\TechSupport;
use App\Repository\TechSupport\TechSupportRepository;
use Firebase\JWT\JWT;
use Symfony\Component\HttpFoundation\JsonResponse;

/**
 * GET /api/tech-supports/{id}/subscribe
 *
 * Аналог ApiGetChatSubscribeTokenController (см. Chat) — выдаёт
 * короткоживущий Mercure JWT на топик "tech-support:{id}".
 *
 * Доступ НАМЕРЕННО у́же, чем у ApiGetTechSupportController (обычный GET):
 * только автор тикета и КОНКРЕТНО назначенный на него администрант — это
 * приватный канал переписки между этими двумя, а не общий инструмент
 * мониторинга для любого ROLE_ADMIN. Пока администрант не назначен,
 * подписаться может только автор. Остальные админы по-прежнему видят
 * тикет через обычный GET, просто без live-канала на него.
 */
class ApiGetTechSupportSubscribeTokenController extends AbstractApiHelperController
{
    public function __construct(
        private readonly TechSupportRepository $techSupportRepository,
        private readonly string                $mercureJwtSecret, // bind из services.yaml, общий с чатом
    ) {}

    public function __invoke(int $id): JsonResponse
    {
        $bearer = $this->checkedUser();

        /** @var TechSupport|null $techSupport */
        $techSupport = $this->techSupportRepository->find($id);

        if (!$techSupport)
            return $this->errorJson(AppMessages::RESOURCE_NOT_FOUND);

        if ($techSupport->getAuthor() !== $bearer && $techSupport->getAdministrant() !== $bearer)
            return $this->errorJson(AppMessages::OWNERSHIP_MISMATCH);

        $topic = "tech-support:{$id}";

        $token = JWT::encode(
            payload: [
                'mercure' => ['subscribe' => [$topic]],
                'exp'     => time() + 3600,
            ],
            key: $this->mercureJwtSecret,
            alg: 'HS256',
        );

        return $this->json([
            'token' => $token,
            'topic' => $topic,
        ]);
    }
}
