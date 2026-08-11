<?php

namespace App\Dto\TechSupport;

use App\Dto\Image\ImageObjectInput;

class TechSupportPatchInput extends TechSupportInput
{
    // Единственное поле, доступное автору тикета. Остальные (title/reason/
    // priority/description/images, унаследованные от TechSupportInput) —
    // только для админа, см. ApiPatchTechSupportController::applyChanges().
    public ?string $status = null;

    /** @var ImageObjectInput[] */
    public array $images = [];
}
