<?php

namespace App\Controller\Admin\Extra;

use App\Entity\Extra\EntityRevision;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextEditorField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Filter\ChoiceFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\DateTimeFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\EntityFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\NumericFilter;

/**
 * Просмотр audit trail в админке. Ничего, кроме retention (expiresAt),
 * редактировать нельзя — записи пишутся только листенерами (см.
 * TicketListener/ChatMessageListener/etc.), руками их заводить или менять
 * задним числом не должно быть возможности, иначе теряется весь смысл
 * audit trail. New и Delete отключены целиком — единственный легитимный
 * способ удалить запись — дождаться retention (app:prune-entity-revisions).
 */
class EntityRevisionCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return EntityRevision::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return parent::configureCrud($crud)
            ->setEntityPermission('ROLE_SUPER_ADMIN')
            ->setEntityLabelInPlural('Аудит изменений')
            ->setEntityLabelInSingular('запись аудита')
            ->setPageTitle(Crud::PAGE_EDIT, 'Retention записи аудита')
            ->setPageTitle(Crud::PAGE_DETAIL, 'Информация о записи аудита')
            ->setDefaultSort(['createdAt' => 'DESC']);
    }

    public function configureActions(Actions $actions): Actions
    {
        $actions->add(Crud::PAGE_INDEX, Action::DETAIL);

        return parent::configureActions($actions)
            ->disable(Action::NEW, Action::DELETE)
            ->setPermissions([
                Action::EDIT => 'ROLE_SUPER_ADMIN',
            ]);
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters
            ->add(ChoiceFilter::new('entityType', 'Тип сущности')->setChoices(EntityRevision::ENTITY_TYPES))
            ->add(ChoiceFilter::new('action', 'Действие')->setChoices(EntityRevision::ACTIONS))
            ->add(NumericFilter::new('entityId', 'ID сущности'))
            ->add(NumericFilter::new('parentId', 'ID родителя'))
            ->add(ChoiceFilter::new('entity', 'Сущность')->setChoices(array_flip(EntityRevision::ENTITIES)))
            ->add(EntityFilter::new('actor', 'Кто изменил'))
            ->add(DateTimeFilter::new('createdAt', 'Создано'))
            ->add(DateTimeFilter::new('expiresAt', 'Истекает (retention)'));
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id')
            ->hideOnForm();

        yield ChoiceField::new('entityType', 'Тип сущности')
            ->setChoices(EntityRevision::ENTITY_TYPES)
            ->setDisabled()
            ->setColumns(2);

        yield IntegerField::new('entityId', 'ID сущности')
            ->setDisabled()
            ->setColumns(2);

        // ID прямого родителя (Chat/Ticket/TechSupport — см. EntityRevision::
        // $parentId), пусто для entityType=ticket, у него родителя нет.
        yield IntegerField::new('parentId', 'ID родителя')
            ->setDisabled()
            ->setHelp('Пусто у "Объявление / услуга" — Ticket корень иерархии, родителя у него нет.')
            ->setColumns(2);

        // Класс сущности, которой принадлежит parentId (или сам себе, см.
        // EntityRevision::$entity/ENTITIES) — "Ticket"/"TechSupport"/... в
        // человекочитаемом виде, отдельно от entityType (тип ЭТОЙ записи).
        yield ChoiceField::new('entity', 'Сущность')
            ->setChoices(array_flip(EntityRevision::ENTITIES))
            ->setDisabled()
            ->setColumns(2);

        yield ChoiceField::new('action', 'Действие')
            ->setChoices(EntityRevision::ACTIONS)
            ->renderAsBadges([
                EntityRevision::ACTION_UPDATED => 'warning',
                EntityRevision::ACTION_DELETED => 'danger',
            ])
            ->setDisabled()
            ->setColumns(2);

        yield AssociationField::new('actor', 'Кто изменил')
            ->setDisabled()
            ->setColumns(2);

        // Снимок данных автора на момент записи — переживает удаление
        // аккаунта (см. EntityRevision::$actorLabel/$actorId/$actorName/
        // $actorSurname), поэтому видны даже когда "Кто изменил" выше уже
        // пуст. setHelp — только на первом, чтобы не повторять одно и то же
        // 4 раза подряд.
        yield TextField::new('actorLabel', 'Email автора (на момент правки)')
            ->setDisabled()
            ->setHelp('Снимок на момент записи, заполняется автоматически. Переживает удаление аккаунта — единственный способ узнать автора, если его уже нет в системе.')
            ->hideOnIndex()
            ->setColumns(3);

        yield IntegerField::new('actorId', 'ID автора (на момент правки)')
            ->setDisabled()
            ->hideOnIndex()
            ->setColumns(1);

        yield TextField::new('actorName', 'Имя автора (на момент правки)')
            ->setDisabled()
            ->hideOnIndex()
            ->setColumns(2);

        yield TextField::new('actorSurname', 'Фамилия автора (на момент правки)')
            ->setDisabled()
            ->hideOnIndex()
            ->setColumns(2);

        // Биндим на виртуальные геттеры, а не на реальное поле snapshot
        // (Doctrine json-колонка) напрямую — EasyAdmin определяет тип
        // виджета по маппингу колонки и падает на "Array to string
        // conversion" независимо от класса Field, если биндить напрямую на
        // json-свойство.
        //
        // ДВА поля на одни и те же данные, не одно — DETAIL и EDIT в
        // EasyAdmin рендерятся принципиально разными путями: DETAIL — через
        // setTemplateName() (вывод под моим контролем), EDIT — всегда через
        // настоящий Symfony Form-виджет конкретного класса Field, который
        // САМ, дополнительно, экранирует переданное значение. Раньше здесь
        // было одно HTML-поле (TextEditorField/Trix) — на DETAIL оно
        // работало верно, а на EDIT удваивало экранирование ("<br>"
        // показывался как буквальный текст "&lt;br&gt;", уже экранированный
        // "&lt;div&gt;" — как "&amp;lt;div&amp;gt;", проверено живьём);
        // единственным рабочим вариантом было прятать его с формы насовсем
        // (->hideOnForm()) — из-за чего "было → стало" пропадало при
        // редактировании reason/expiresAt (единственные реально
        // редактируемые здесь поля). Теперь вместо этого — раздельные поля:
        //   - snapshotSummary (HTML, "<br>") — только DETAIL, raw HTML.
        //   - snapshotSummaryPlain (текст, "\n") — только EDIT, обычный
        //     <textarea> (не Trix): значение НЕ предэкранировано, поэтому
        //     единственное экранирование самого Form-виджета — корректное,
        //     а "\n" браузер сам показывает как перенос строки
        //     (white-space: pre у textarea), без нужды в "<br>".
        yield TextEditorField::new('snapshotSummary', 'Что изменилось (было → стало)')
            ->setTemplateName('crud/field/text')
            ->setDisabled()
            ->onlyOnDetail()
            ->setColumns(12);

        yield TextareaField::new('snapshotSummaryPlain', 'Что изменилось (было → стало)')
            ->setDisabled()
            ->setNumOfRows(8)
            ->onlyOnForms()
            ->setColumns(12);

        // Единственное второе (кроме retention) редактируемое поле —
        // причина заполняется админом вручную, ни один листенер её не
        // проставляет (см. EntityRevision::$reason).
        yield TextEditorField::new('reason', 'Причина')
            ->setRequired(false)
            ->hideOnIndex()
            ->setColumns(12);

        yield DateTimeField::new('createdAt', 'Создано')
            ->setDisabled()
            ->setColumns(6);

        // Единственное реально редактируемое поле — retention.
        yield DateTimeField::new('expiresAt', 'Истекает (retention)')
            ->setRequired(false)
            ->setHelp('Пусто = хранится бессрочно, retention отключён для этой записи. app:prune-entity-revisions удалит запись только после этой даты.')
            ->setColumns(6);
    }
}
