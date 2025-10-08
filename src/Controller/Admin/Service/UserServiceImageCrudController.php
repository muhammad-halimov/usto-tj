<?php

namespace App\Controller\Admin\Service;

use App\Controller\Admin\Field\VichImageField;
use App\Entity\Service\UserServiceImage;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;

class UserServiceImageCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return UserServiceImage::class;
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id')
            ->onlyOnIndex();

        yield VichImageField::new('imageFile', 'Фото услуги')
            ->setHelp('
                <div class="mt-3">
                    <span class="badge badge-info">*.jpg</span>
                    <span class="badge badge-info">*.jpeg</span>
                    <span class="badge badge-info">*.png</span>
                    <span class="badge badge-info">*.jiff</span>
                    <span class="badge badge-info">*.webp</span>
                </div>
            ')
            ->onlyOnForms()
            ->setColumns(12);
    }
}
