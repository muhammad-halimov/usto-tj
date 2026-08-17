<?php

namespace App\Controller\Api\CRUD\GET\Chat\Chat;

use App\ApiResource\AppMessages;
use App\Controller\Api\CRUD\Abstract\AbstractApiGetController;
use App\Entity\Chat\Chat;
use App\Entity\Trait\Readable\G;
use App\Entity\User;
use App\Repository\Chat\ChatMessageRepository;
use App\Service\Extra\LocalizationService;
use Symfony\Component\HttpFoundation\JsonResponse;

class ApiGetChatController extends AbstractApiGetController
{
    // Ставится в checkOwnership() — buildResponse() своего bearer не получает
    // (сигнатура задана в AbstractApiGetController), а он нужен для
    // countUnread() ("непрочитанные" — по отношению к ТЕКУЩЕМУ читателю).
    private ?User $bearer = null;

    public function __construct(
        private readonly LocalizationService   $localizationService,
        private readonly ChatMessageRepository $chatMessageRepository,
    ) {}

    protected function getEntityClass(): string { return Chat::class; }

    protected function setSerializationGroups(): array { return G::OPS_CHATS; }

    protected function checkOwnership(object $entity, ?User $bearer): ?JsonResponse
    {
        /** @var Chat $entity */
        if ($entity->getAuthor() !== $bearer && $entity->getReplyAuthor() !== $bearer)
            return $this->errorJson(AppMessages::OWNERSHIP_MISMATCH);

        $this->bearer = $bearer;

        return null;
    }

    protected function buildResponse(object|array $entity): JsonResponse
    {
        /** @var Chat $entity */
        if ($entity->getAuthor()) $this->localizationService->localizeUser($entity->getAuthor(), $this->getLocale());
        if ($entity->getReplyAuthor()) $this->localizationService->localizeUser($entity->getReplyAuthor(), $this->getLocale());

        // Тот же расчёт превью/бейджа, что и в списке /chats/me
        // (см. ApiGetMyChatsController) — для консистентности формы ответа.
        $entity->setLastMessage($this->chatMessageRepository->findLastMessage($entity));
        if ($this->bearer) $entity->setUnreadCount($this->chatMessageRepository->countUnread($entity, $this->bearer));

        return parent::buildResponse($entity);
    }

    protected function afterFetch(array|object $entity, ?User $user): void
    {
        /** @var Chat $chat */
        foreach ($entity as $chat) {
            if ($chat->getAuthor()) $this->localizationService->localizeUser($chat->getAuthor(), $this->getLocale());
            if ($chat->getReplyAuthor()) $this->localizationService->localizeUser($chat->getReplyAuthor(), $this->getLocale());
        }
    }
}
