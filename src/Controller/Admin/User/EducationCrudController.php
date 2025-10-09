<?php

namespace App\Controller\Admin\User;

use App\Entity\User\Education;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

class EducationCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return Education::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return parent::configureCrud($crud)
            ->setEntityPermission('ROLE_ADMIN')
            ->setEntityLabelInPlural('Образование')
            ->setEntityLabelInSingular('образование')
            ->setPageTitle(Crud::PAGE_NEW, 'Добавление образования')
            ->setPageTitle(Crud::PAGE_EDIT, 'Изменение образования')
            ->setPageTitle(Crud::PAGE_DETAIL, "Информация об образовании");
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

        yield TextField::new('uniTitle', 'Название ВУЗа')
            ->setColumns(12)
            ->setRequired(true);

        yield IntegerField::new('beginning', 'Начало обучения')
            ->setColumns(12)
            ->setRequired(true);

        yield IntegerField::new('ending', 'Окончание обучения')
            ->setColumns(12);

        yield BooleanField::new('graduated', 'Окончено')
            ->setColumns(12);
    }
}
