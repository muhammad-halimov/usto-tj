<?php

namespace App\Controller\Api\CRUD\DELETE\Chat\Chat;

use App\ApiResource\AppMessages;
use App\Controller\Api\CRUD\Abstract\AbstractApiHelperController;
use App\Entity\Chat\Chat;
use App\Repository\Chat\ChatRepository;
use Symfony\Component\HttpFoundation\JsonResponse;

/**
 * DELETE /api/chats/{id} — "Удалить чат для меня".
 *
 * Раньше это был безусловный физический DELETE: любой участник мог удалить
 * чат целиком одним запросом, без согласия второго. Теперь — мягкое скрытие
 * per-участник (см. Chat::$hiddenByAuthor/$hiddenByReplyAuthor): запрос
 * ставит собственный флаг звонящего, чат пропадает из его /chats/me
 * (см. ChatRepository::findUserChats()), но остаётся видимым второй стороне.
 * Только когда ОБА флага стали true — чат реально удаляется, тем же запросом,
 * который это обнаружил (без отдельной cron/ручной уборки).
 *
 * Не extends AbstractApiDeleteController: тот шаблон жёстко завершается
 * безусловным remove() сразу после ownership-проверки (__invoke() там final),
 * а здесь удаление — не единственный, а условный исход.
 */
class ApiDeleteChatController extends AbstractApiHelperController
{
    public function __construct(private readonly ChatRepository $chatRepository) {}

    public function __invoke(int $id): JsonResponse
    {
        $bearer = $this->checkedUser();

        $chat = $this->chatRepository->find($id);
        if (!$chat) return $this->errorJson(AppMessages::CHAT_NOT_FOUND);

        if ($chat->getAuthor() !== $bearer && $chat->getReplyAuthor() !== $bearer)
            return $this->errorJson(AppMessages::OWNERSHIP_MISMATCH);

        if ($chat->getAuthor() === $bearer) {
            $chat->setHiddenByAuthor(true);
        } else {
            $chat->setHiddenByReplyAuthor(true);
        }

        // Оба участника скрыли чат для себя — реально удаляем. cascade: ['all']
        // на Chat::$messages уже тянет за собой ChatMessage и их фото.
        if ($chat->getHiddenByAuthor() && $chat->getHiddenByReplyAuthor()) {
            $this->entityManager->remove($chat);
        }

        $this->flush();

        return $this->json(null, 204);
    }
}
