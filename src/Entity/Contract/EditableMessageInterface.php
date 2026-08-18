<?php

namespace App\Entity\Contract;

use DateTimeImmutable;

/**
 * Общий контракт для "сообщений" с одинаковыми правилами редактирования —
 * ChatMessage, TechSupportMessage. Правила (24ч-лимит, запрет полной замены
 * текста, мягкое удаление с переведённым плейсхолдером — см.
 * AbstractApiHelperController::DELETED_MESSAGE_PLACEHOLDER/softDeleteMessage())
 * реализованы ОДИН раз в AbstractApiHelperController и работают через этот
 * интерфейс — не дублируются в каждом контроллере отдельно.
 */
interface EditableMessageInterface extends HasImagesInterface
{
    public function getDescription(): ?string;

    public function setDescription(?string $description): static;

    public function getCreatedAt(): DateTimeImmutable;

    public function setUpdatedAt(): void;

    /** Было ли сообщение хоть раз отредактировано (не сбрасывается обратно). */
    public function isEdited(): bool;

    public function setEdited(bool $edited): static;

    /** Мягко удалено автором — см. DELETED_PLACEHOLDER. */
    public function isDeletedByAuthor(): bool;

    public function setDeletedByAuthor(bool $deletedByAuthor): static;
}
