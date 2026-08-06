<?php

namespace App\Controller\Api\CRUD\GET\TechSupport\TechSupport;

use App\Controller\Api\CRUD\Abstract\AbstractApiHelperController;
use App\Entity\TechSupport\TechSupport;
use App\Repository\TechSupport\TechSupportRepository;
use Firebase\JWT\JWT;
use Symfony\Component\HttpFoundation\JsonResponse;

/**
 * GET /api/tech-supports/inbox-token
 *
 * Аналог ApiGetInboxTokenController (см. Chat) — выдаёт единый Mercure
 * JWT-токен для подписки на ВСЕ тикеты техподдержки пользователя разом:
 * где он автор ИЛИ назначенный администрант — тот же охват, что у
 * GET /tech-supports/me. Фронтенд одним SSE-соединением получает события
 * из всех тикетов сразу, без захода в каждый по отдельности (например,
 * для бабла непрочитанных обращений).
 */
class ApiGetTechSupportInboxTokenController extends AbstractApiHelperController
{
    public function __construct(
        private readonly TechSupportRepository $techSupportRepository,
        private readonly string                $mercureJwtSecret, // bind из services.yaml, общий с чатом
    ) {}

    public function __invoke(): JsonResponse
    {
        $user = $this->checkedUser();

        /** @var TechSupport[] $tickets */
        $tickets = $this->techSupportRepository->findTechSupportsByUserOrAdmin($user)->getQuery()->getResult();

        $topics = array_values(
            array_filter(
                array_map(fn(TechSupport $t) => $t->getId() ? "tech-support:{$t->getId()}" : null, $tickets)
            )
        );

        if (empty($topics)) {
            return $this->json(['token' => null, 'topics' => []]);
        }

        $token = JWT::encode(
            payload: [
                'mercure' => ['subscribe' => $topics],
                'exp'     => time() + 3600,
            ],
            key: $this->mercureJwtSecret,
            alg: 'HS256',
        );

        return $this->json(['token' => $token, 'topics' => $topics]);
    }
}
