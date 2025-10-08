<?php

namespace App\Controller\Admin\Ticket;

use App\Entity\Ticket\UserTicket;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\CollectionField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\NumberField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextEditorField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

class UserTicketCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return UserTicket::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return parent::configureCrud($crud)
            ->setEntityPermission('ROLE_ADMIN')
            ->setEntityLabelInPlural('Объявления')
            ->setEntityLabelInSingular('объявление')
            ->setPageTitle(Crud::PAGE_NEW, 'Добавление объявления')
            ->setPageTitle(Crud::PAGE_EDIT, 'Изменение объявления')
            ->setPageTitle(Crud::PAGE_DETAIL, "Информация о объявлении");
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

        yield AssociationField::new('category', 'Категория')
            ->setRequired(true)
            ->setColumns(3);

        yield AssociationField::new('author', 'Клиент')
            ->setRequired(true)
            ->setColumns(3);

        yield TextField::new('title', 'Название')
            ->setColumns(4)
            ->setRequired(true);

        yield NumberField::new('budget', 'Бюджет')
            ->setRequired(true)
            ->setNumDecimals(1)
            ->setColumns(2);

        yield TextEditorField::new('description', 'Описание')
            ->setRequired(true)
            ->setColumns(6);

        yield TextEditorField::new('notice', 'Доп. описание')
            ->setRequired(true)
            ->setColumns(6);

        yield CollectionField::new('userTicketImages', 'Галерея изображений')
            ->useEntryCrudForm(UserTicketImageCrudController::class)
            ->hideOnIndex()
            ->setColumns(12)
            ->setRequired(false);

        yield DateTimeField::new('updatedAt', 'Обновлено')
            ->onlyOnIndex();

        yield DateTimeField::new('createdAt', 'Создано')
            ->onlyOnIndex();
    }
}
