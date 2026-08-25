<?php

namespace App\Controller\Admin\Extra;

use App\Entity\Extra\OAuthProvider;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

/**
 * Админка над OAuthProvider — обычно открывается не напрямую из меню, а
 * как вложенная CollectionField-форма внутри UserCrudController (поле
 * "oauthProviders", ->useEntryCrudForm(self::class)) — читай: список
 * привязок конкретного юзера. Только просмотр/ручное редактирование
 * значений — сама привязка создаётся кодом (OAuth-логин/link), не отсюда.
 */
class OAuthProviderCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return OAuthProvider::class;
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

        yield TextField::new('provider', 'Провайдер')
            ->setColumns(6)
            ->setRequired(true);

        yield TextField::new('providerId', 'ID провайдера')
            ->setColumns(6)
            ->setRequired(true);

        yield DateTimeField::new('createdAt', 'Создано')
            ->setColumns(6)
            ->setDisabled();

        yield DateTimeField::new('updatedAt', 'Обновлено')
            ->setColumns(6)
            ->setDisabled();
    }
}
