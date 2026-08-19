<?php

namespace App\Dto\Review;

use App\Dto\Image\ImageObjectInput;

class ReviewPatchInput
{
    public float   $rating      = 0;
    public ?string $description = null;

    // Nullable, а не [] по умолчанию — иначе "images не переданы" и "images
    // явно отправлены пустым массивом" (удалить последнее фото) неразличимы
    // в контроллере. Раньше это было хуже, чем просто неразличимо: контроллер
    // вообще не проверял это поле и на КАЖДЫЙ PATCH безусловно стирал все
    // фото отзыва и пересобирал из $dto->images — правка одного rating без
    // упоминания images удаляла все фото. См. ApiPatchReviewController.
    /** @var ImageObjectInput[]|null */
    public ?array $images = null;
}
