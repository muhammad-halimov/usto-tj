<?php

namespace App\Controller\Api\CRUD\GET\Chat\Chat;

use App\Controller\Api\CRUD\Abstract\AbstractApiGetCollectionController;
use App\Entity\Trait\Readable\G;
use App\Entity\User;
use App\Repository\Chat\ChatMessageRepository;
use App\Repository\Chat\ChatRepository;
use App\Service\Extra\LocalizationService;
use Doctrine\ORM\QueryBuilder;

/**
 * GET /api/chats/{id}/messages — постраничный список сообщений чата
 * (новые сначала), отдельно от GET /api/chats/{id}.
 *
 * Раньше все сообщения чата отдавались одним массивом прямо внутри Chat
 * (Chat::$messages, без пагинации) — неограниченно растущий ответ на
 * длинной переписке. Теперь Chat::$messages не сериализуется вообще
 * (осталось только как внутренняя ORM-связь, см. Chat.php), сообщения —
 * только через этот эндпоинт.
 *
 * Доступ — как и везде у Chat: только автор или ответчик самого чата.
 * Несуществующий чат и "чужой" чат намеренно неразличимы снаружи — оба
 * дают 404 (см. fetchQuery() → null → RESOURCE_NOT_FOUND в
 * AbstractApiGetCollectionController), не раскрываем факт существования
 * чужого чата (тот же принцип, что в CurrentUserCollectionExtension).
 */
class ApiGetChatMessagesController extends AbstractApiGetCollectionController
{
    public function __construct(
        private readonly ChatRepository        $chatRepository,
        private readonly ChatMessageRepository $chatMessageRepository,
        private readonly LocalizationService   $localizationService,
    ) {}

    protected function setSerializationGroups(): array { return G::OPS_CHAT_MSGS; }

    protected function fetchQuery(User $user): ?QueryBuilder
    {
        $id = $this->requestStack->getCurrentRequest()?->attributes->get('id');
        if (!$id) return null;

        // (int) убран (06.09.2026, переход на UUID-PK) — Chat::$id теперь
        // UUID-строка.
        $chat = $this->chatRepository->find($id);
        if (!$chat) return null;

        if ($chat->getAuthor() !== $user && $chat->getReplyAuthor() !== $user) return null;

        return $this->chatMessageRepository->findByChat($chat);
    }

    protected function afterFetch(array|object $entity, ?User $user): void
    {
        foreach ($entity as $message) {
            if ($message->getAuthor()) $this->localizationService->localizeUser($message->getAuthor(), $this->getLocale());
        }
    }
}
