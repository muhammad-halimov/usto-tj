<?php

namespace App\Controller\Api\CRUD\PATCH\TechSupport\Message;

use App\ApiResource\AppMessages;
use App\Controller\Api\CRUD\Abstract\AbstractApiPatchController;
use App\Dto\TechSupport\TechSupportMessagePatchInput;
use App\Entity\TechSupport\TechSupport;
use App\Entity\TechSupport\TechSupportMessage;
use App\Entity\User;
use App\Repository\TechSupport\TechSupportMessageRepository;
use Symfony\Component\HttpFoundation\JsonResponse;

class ApiPatchTechSupportMessageController extends AbstractApiPatchController
{
    // Храним techSupport после checkOwnership — нужен только для buildResponse (возврат ID тикета).
    // Намеренно НЕ берём из тела запроса: раньше здесь был баг — пользователь мог передать
    // чужой techSupportId, пройти ownership-check и редактировать/перемещать чужие сообщения.
    private ?TechSupport $techSupport = null;

    public function __construct(private readonly TechSupportMessageRepository $techSupportMessageRepository) {}

    protected function getInputClass(): string { return TechSupportMessagePatchInput::class; }

    protected function getEntityById(int $id): ?object
    {
        // Ищем само сообщение по ID — только из базы, без доверия к данным запроса.
        return $this->entityManager->find(TechSupportMessage::class, $id);
    }

    protected function checkOwnership(object $entity, ?User $bearer): ?JsonResponse
    {
        /** @var TechSupportMessage $entity */
        // Тикет берём из самой сущности сообщения, а не из тела запроса.
        // Это ключевая проверка безопасности: мы доверяем только тому, что лежит в БД.
        $techSupport = $entity->getTechSupport();

        if (!$techSupport) return $this->errorJson(AppMessages::TECH_SUPPORT_NOT_FOUND);

        // Редактировать сообщение может только автор тикета или его администрант.
        if ($techSupport->getAdministrant() !== $bearer && $techSupport->getAuthor() !== $bearer)
            return $this->errorJson(AppMessages::OWNERSHIP_MISMATCH);

        // Удалённое (мягко, см. EditableMessageInterface::DELETED_PLACEHOLDER)
        // сообщение больше не редактируется — плейсхолдер не текст, который
        // можно "поправить".
        if ($entity->isDeletedByAuthor())
            return $this->errorJson(AppMessages::MESSAGE_ALREADY_DELETED);

        // "Правка до реакции оператора" — только когда редактирует именно
        // обращатель (автор тикета); администрант правит свои же сообщения
        // без этого гейта, реакция оператора на самого себя — бессмысленное
        // условие. Реакция = прочитал (readAt) ИЛИ уже ответил после этого
        // сообщения (см. existsAdministrantMessageAfter — у TechSupportMessage
        // нет своего replyTo как у ChatMessage, поэтому "ответил" здесь —
        // администрант написал в тот же тикет ПОЗЖЕ этого сообщения).
        if ($bearer === $techSupport->getAuthor()
            && ($entity->getReadAt() !== null || $this->techSupportMessageRepository->existsAdministrantMessageAfter($entity))) {
            return $this->errorJson(AppMessages::TECH_SUPPORT_MESSAGE_EDIT_LOCKED);
        }

        // 15 минут с момента отправки — на обоих участников одинаково (в
        // отличие от гейта выше это не про "реакцию", а про то, что старую
        // переписку в принципе нельзя переписывать задним числом). Тот же
        // хелпер, что у Review/ChatMessage, но короче окно (см.
        // AbstractApiHelperController::MESSAGE_EDIT_WINDOW).
        if ($this->isPastEditWindow($entity->getCreatedAt(), self::MESSAGE_EDIT_WINDOW))
            return $this->errorJson(AppMessages::EDIT_WINDOW_EXPIRED);

        $this->techSupport = $techSupport;

        return null;
    }

    protected function applyChanges(object $entity, User $bearer, object $dto): ?JsonResponse
    {
        /** @var TechSupportMessage $entity */
        /** @var TechSupportMessagePatchInput $dto */
        $text        = $dto->description;
        $imagesParam = $dto->images;

        if ($text === null && empty($imagesParam))
            return $this->errorJson(AppMessages::NOTHING_TO_UPDATE);

        // Обновляем текст сообщения.
        // Раньше здесь был баг: вызывался setAuthor($bearer), что перезаписывало
        // оригинального автора сообщения на того, кто его редактирует.
        if ($text !== null) {
            // Не позволяем стереть текст и вписать полностью другой в рамках
            // "правки" — если бы это работало, лимит на редактирование выше
            // ничего бы не значил (можно было бы просто переписать заново).
            if ($this->isEditTooDifferent($entity->getDescription(), $text))
                return $this->errorJson(AppMessages::EDIT_TOO_DIFFERENT);

            $entity->setDescription($text);
            $entity->setEdited(true);
        }

        // Тот же механизм синхронизации фоток, что и в PATCH сообщений чата
        // (ApiPatchChatMessageController) — реордер/удаление по имени файла.
        if (!empty($imagesParam)) {
            $this->syncImages($entity, $imagesParam, $bearer);
        }

        $entity->setUpdatedAt();

        return null;
    }

    protected function buildResponse(object|array $entity): JsonResponse
    {
        /** @var TechSupportMessage $entity */
        return $this->json([
            'techSupport' => ['id' => $this->techSupport->getId()],
            'message'     => ['id' => $entity->getId()],
        ]);
    }
}
