<?php

namespace App\Dto\Image;

use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\Validator\Constraints as Assert;

class ImageInput
{
    /**
     * @var File[]
     */
    #[Assert\NotBlank(message: 'imageFile is required')]
    #[Assert\Count(min: 1, minMessage: 'At least one file is required')]
    #[Assert\All([
        new Assert\File(
            maxSize: '10M',
            mimeTypes: ['image/png', 'image/jpeg', 'image/jpg', 'image/webp'],
            maxSizeMessage: 'File is too large ({{ size }}). Maximum allowed size is {{ limit }}.',
            mimeTypesMessage: 'Invalid image format',
        ),
    ])]
    public array $imageFile = [];
}
