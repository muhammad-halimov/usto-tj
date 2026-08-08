<?php

namespace App\Controller\Admin\Extra;

use App\Controller\Admin\Field\VichImageField;
use App\Entity\Extra\MultipleImage;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use App\Controller\Admin\Traits\VichImageHelpTrait;

class MultipleImageCrudController extends AbstractCrudController
{
    use VichImageHelpTrait;

    public static function getEntityFqcn(): string
    {
        return MultipleImage::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return parent::configureCrud($crud)
            ->setDefaultSort(['createdAt' => 'DESC']);
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id')
            ->hideOnForm();

        yield VichImageField::new('imageFile', 'Фото')
            ->setHelp($this->vichImageBadgeHelp())
            ->onlyOnForms()
            ->setColumns(12);

        yield DateTimeField::new('createdAt', 'Создано')
            ->setDisabled()
            ->setColumns(12);

        yield DateTimeField::new('updatedAt', 'Обновлено')
            ->setDisabled()
            ->setColumns(12);
    }
}
