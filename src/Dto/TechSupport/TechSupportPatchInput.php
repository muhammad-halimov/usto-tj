<?php

namespace App\Dto\TechSupport;

use App\Dto\Image\ImageObjectInput;

class TechSupportPatchInput extends TechSupportInput
{
    // Кто что может патчить — см. ApiPatchTechSupportController::applyChanges():
    // автор — status (по AUTHOR_TRANSITIONS) + title/description/images (24ч
    // окно); reason/priority (унаследованы от TechSupportInput) — только админ.
    public ?string $status = null;

    // Nullable, а не [] по умолчанию — иначе "images не переданы" и "images
    // явно отправлены пустым массивом" (удалить последнее фото) неразличимы
    // в контроллере (см. GalleryPatchInput — тот же паттерн, уже правильный).
    /** @var ImageObjectInput[]|null */
    public ?array $images = null;
}
