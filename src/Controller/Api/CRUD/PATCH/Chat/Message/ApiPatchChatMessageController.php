<?php

namespace App\Controller\Api\CRUD\PATCH\Chat\Message;

use App\ApiResource\AppMessages;
use App\Controller\Api\CRUD\Abstract\AbstractApiPatchController;
use App\Dto\Chat\ChatMessagePatchInput;
use App\Entity\Chat\ChatMessage;
use App\Entity\Trait\Readable\G;
use App\Entity\User;
use App\Service\Extra\LocalizationService;
use Symfony\Component\HttpFoundation\JsonResponse;

class ApiPatchChatMessageController extends AbstractApiPatchController
{
    public function __construct(private readonly LocalizationService $localizationService) {}

    protected function getNotFoundError(): string { return AppMessages::CHAT_MESSAGE_NOT_FOUND; }

    protected function setSerializationGroups(): array { return G::OPS_CHAT_MSGS; }

    protected function getInputClass(): string { return ChatMessagePatchInput::class; }

    protected function afterFetch(object|array $entity, ?User $user): void
    {
        /** @var ChatMessage $entity */
        if ($entity->getAuthor()) $this->localizationService->localizeUser($entity->getAuthor(), $this->getLocale());
    }

    protected function getEntityById(string $id): ?object
    {
        return $this->entityManager->find(ChatMessage::class, $id);
    }

    protected function checkOwnership(object $entity, ?User $bearer): ?JsonResponse
    {
        /** @var ChatMessage $entity */
        // Раньше здесь был баг: чат для ownership-проверки брался из
        // $dto->chat (тела запроса), а не из реальной связи сообщения —
        // тот же класс уязвимости, что уже был пофикшен в
        // ApiPatchTechSupportMessageController. Пользователь мог прислать
        // ЛЮБОЙ чат, где он участник, и пройти проверку для сообщения,
        // которое на самом деле принадлежит совсем другому чату.
        $chat = $entity->getChat();

        if (!$chat) return $this->errorJson(AppMessages::CHAT_NOT_FOUND);

        if ($chat->getAuthor() !== $bearer && $chat->getReplyAuthor() !== $bearer)
            return $this->errorJson(AppMessages::OWNERSHIP_MISMATCH);

        // Удалённое (мягко, см. AbstractApiHelperController::softDeleteMessage)
        // сообщение больше не редактируется.
        if ($entity->isDeletedByAuthor())
            return $this->errorJson(AppMessages::MESSAGE_ALREADY_DELETED);

        // 15 минут с момента отправки — так же у TechSupportMessage, короче,
        // чем у Review (см. AbstractApiHelperController::MESSAGE_EDIT_WINDOW).
        if ($this->isPastEditWindow($entity->getCreatedAt(), self::MESSAGE_EDIT_WINDOW))
            return $this->errorJson(AppMessages::EDIT_WINDOW_EXPIRED);

        return null;
    }

    protected function applyChanges(object $entity, User $bearer, object $dto): ?JsonResponse
    {
        /** @var ChatMessage $entity */
        /** @var ChatMessagePatchInput $dto */
        $text        = $dto->description;
        $imagesParam = $dto->images;

        // === null, не empty() — иначе явное "images": [] (удалить последнее
        // фото) неотличимо от "images вообще не прислали" (оба empty()).
        if ($text === null && $imagesParam === null)
            return $this->errorJson(AppMessages::NOTHING_TO_UPDATE);

        // Не даём отредактировать сообщение так, чтобы не осталось ни
        // текста, ни фото (см. wouldLeaveMessageEmpty()) — до применения
        // изменений, чтобы не трогать сущность, если откажем.
        if ($this->wouldLeaveMessageEmpty($entity, $text, $imagesParam))
            return $this->errorJson(AppMessages::MESSAGE_EMPTY);

        if ($text !== null) {
            // Не даём стереть текст и вписать полностью другой в рамках
            // "правки" — иначе лимит на редактирование выше ничего не значил
            // бы (можно было бы просто переписать сообщение с нуля).
            if ($this->isEditTooDifferent($entity->getDescription(), $text))
                return $this->errorJson(AppMessages::EDIT_TOO_DIFFERENT);

            $entity->setDescription($text);
            $entity->setEdited(true);
        }

        if ($imagesParam !== null) {
            $this->syncImages($entity, $imagesParam, $bearer);
        }

        $entity->setUpdatedAt();

        return null;
    }
}
