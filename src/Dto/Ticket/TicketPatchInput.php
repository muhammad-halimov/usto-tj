<?php

namespace App\Dto\Ticket;

use App\Dto\Image\ImageObjectInput;

class TicketPatchInput extends TicketInput
{
    // Nullable, а не [] по умолчанию — иначе "images не переданы" и "images
    // явно отправлены пустым массивом" (удалить последнее фото) неразличимы
    // в контроллере (см. GalleryPatchInput — тот же паттерн, уже правильный).
    /** @var ImageObjectInput[]|null */
    public ?array $images = null;
}
