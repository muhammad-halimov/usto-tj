<?php

namespace App\Controller\Admin\Geography;

use App\Entity\Geography\Geography;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextEditorField;

class GeographyCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return Geography::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return parent::configureCrud($crud)
            ->setEntityPermission('ROLE_ADMIN')
            ->setEntityLabelInPlural('География работ')
            ->setEntityLabelInSingular('географию работ')
            ->setPageTitle(Crud::PAGE_NEW, 'Добавление географии')
            ->setPageTitle(Crud::PAGE_EDIT, 'Изменение географии')
            ->setPageTitle(Crud::PAGE_DETAIL, "Информация о географии");
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

        yield ChoiceField::new('province', 'Провинция')
            ->setColumns(4)
            ->setChoices(Geography::PROVINCES)
            ->setRequired(true);

        yield AssociationField::new('city', 'Город')
            ->setColumns(4)
            ->setFormTypeOptions(['by_reference' => false])
            ->setRequired(true);

        yield AssociationField::new('district', 'Район')
            ->setColumns(4)
            ->setFormTypeOptions(['by_reference' => false])
            ->setRequired(true);

        yield TextEditorField::new('description', 'Описание')
            ->setRequired(true)
            ->setColumns(12);

        yield DateTimeField::new('updatedAt', 'Обновлено')
            ->onlyOnIndex();

        yield DateTimeField::new('createdAt', 'Создано')
            ->onlyOnIndex();
    }
}
