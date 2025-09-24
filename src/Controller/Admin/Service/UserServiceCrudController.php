<?php

namespace App\Controller\Admin\Service;

use App\Entity\Service\UserService;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\NumberField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextEditorField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

class UserServiceCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return UserService::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return parent::configureCrud($crud)
            ->setEntityPermission('ROLE_ADMIN')
            ->setEntityLabelInPlural('Услуги')
            ->setEntityLabelInSingular('услугу')
            ->setPageTitle(Crud::PAGE_NEW, 'Добавление услуги')
            ->setPageTitle(Crud::PAGE_EDIT, 'Изменение услуги')
            ->setPageTitle(Crud::PAGE_DETAIL, "Информация об услуге");
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

        yield AssociationField::new('user', 'Мастер')
            ->setRequired(true)
            ->setColumns(6);

        yield TextField::new('title', 'Название')
            ->setRequired(true)
            ->setColumns(6);

        yield AssociationField::new('category', 'Категория')
            ->setRequired(true)
            ->setColumns(6);

        yield NumberField::new('price', 'Цена')
            ->setRequired(true)
            ->setNumDecimals(1)
            ->setColumns(4);

        yield AssociationField::new('userServiceUnit', 'Ед. измерения')
            ->setRequired(true)
            ->setColumns(2);

        yield TextEditorField::new('description', 'Описание')
            ->setRequired(true)
            ->setColumns(12);

        yield DateTimeField::new('updatedAt', 'Обновлено')
            ->onlyOnIndex();

        yield DateTimeField::new('createdAt', 'Создано')
            ->onlyOnIndex();
    }
}
