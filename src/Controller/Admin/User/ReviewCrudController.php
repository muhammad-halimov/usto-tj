<?php

namespace App\Controller\Admin\User;

use App\Entity\User\Review;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\NumberField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextEditorField;

class ReviewCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return Review::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return parent::configureCrud($crud)
            ->setEntityPermission('ROLE_ADMIN')
            ->setEntityLabelInPlural('Отзывы работ')
            ->setEntityLabelInSingular('отзывы работ')
            ->setPageTitle(Crud::PAGE_NEW, 'Добавление отзыва')
            ->setPageTitle(Crud::PAGE_EDIT, 'Изменение отзыва')
            ->setPageTitle(Crud::PAGE_DETAIL, "Информация об отзыве");
    }

    public function configureActions(Actions $actions): Actions
    {
        $actions
            ->add(Crud::PAGE_INDEX, Action::DETAIL);

        $actions
            ->reorder(Crud::PAGE_INDEX, [
                Action::DETAIL,
                Action::EDIT,
                Action::DELETE
            ]);

        return parent::configureActions($actions)
            ->setPermissions([
                Action::NEW => 'ROLE_ADMIN',
                Action::DELETE => 'ROLE_ADMIN',
                Action::EDIT => 'ROLE_ADMIN',
            ]);
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id')
            ->onlyOnIndex();

        yield BooleanField::new('forReviewer', 'Отзыв рецензенту')
            ->addCssClass("form-switch")
            ->setColumns(12);

        yield AssociationField::new('reviewer', 'Рецензент')
            ->setRequired(true)
            ->setColumns(3);

        yield AssociationField::new('user', 'Мастер')
            ->setRequired(true)
            ->setColumns(3);

        yield AssociationField::new('services', 'Услуга')
            ->setFormTypeOptions(['by_reference' => false])
            ->setColumns(3);

        yield NumberField::new('rating', 'Оценка')
            ->setRequired(true)
            ->setNumDecimals(1)
            ->setColumns(3);

        yield TextEditorField::new('description', 'Описание')
            ->setRequired(true)
            ->setColumns(12);

        yield DateTimeField::new('updatedAt', 'Обновлено')
            ->onlyOnIndex();

        yield DateTimeField::new('createdAt', 'Создано')
            ->onlyOnIndex();
    }
}
