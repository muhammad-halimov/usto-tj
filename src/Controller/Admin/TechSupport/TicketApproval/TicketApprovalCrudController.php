<?php

namespace App\Controller\Admin\TechSupport\TicketApproval;

use App\Controller\Admin\Ticket\Ticket\TicketCrudController;
use App\Controller\Admin\Traits\AdminActionsTrait;
use App\Controller\Admin\Traits\NonAdminUserQueryTrait;
use App\Controller\Admin\Traits\TimestampFieldsTrait;
use App\Entity\TechSupport\TicketApproval;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextEditorField;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use Doctrine\ORM\QueryBuilder;

class TicketApprovalCrudController extends AbstractCrudController
{
    use NonAdminUserQueryTrait;

    use TimestampFieldsTrait;

    use AdminActionsTrait;

    public function __construct(private readonly AdminUrlGenerator $adminUrlGenerator) {}

    public static function getEntityFqcn(): string
    {
        return TicketApproval::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return parent::configureCrud($crud)
            ->setEntityPermission('ROLE_SUPER_ADMIN')
            ->setEntityLabelInPlural('Подтверждение объявлений/услуг')
            ->setEntityLabelInSingular('подтверждение')
            ->setPageTitle(Crud::PAGE_NEW, 'Добавление подтверждения')
            ->setPageTitle(Crud::PAGE_EDIT, 'Изменение подтверждения')
            ->setPageTitle(Crud::PAGE_DETAIL, "Информация о подтверждении")
            ->setDefaultSort(['createdAt' => 'DESC']);
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id')
            ->hideOnForm();

        yield BooleanField::new('approved', 'Одобрено')
            ->renderAsSwitch()
            ->setColumns(12);

        yield AssociationField::new('administrant', 'Исполнитель / Админ')
            ->setQueryBuilder($this->adminOnlyQb())
            ->addCssClass('administrant-field')
            ->setColumns(6)
            ->setRequired(true);

        yield AssociationField::new('ticket', 'Объявление / Услуга')
            ->setHelp($this->buildTicketPreviewLink($pageName))
            ->setQueryBuilder(fn (QueryBuilder $qb) => $qb->orderBy('entity.id', 'ASC'))
            ->setColumns(6)
            ->setRequired(true);

        yield TextEditorField::new('description', 'Описание')
            ->setColumns(12);

        yield from $this->timestampFields();
    }

    private function buildTicketPreviewLink(string $pageName): string
    {
        if (!in_array($pageName, [Crud::PAGE_EDIT, Crud::PAGE_DETAIL], true)) {
            return '';
        }

        /** @var TicketApproval|null $ticketApproval */
        $ticketApproval = $this->getContext()?->getEntity()?->getInstance();
        $ticket = $ticketApproval?->getTicket();

        if (!$ticket) return '';

        $url = $this->adminUrlGenerator
            ->setController(TicketCrudController::class)
            ->setAction(Crud::PAGE_EDIT)
            ->setEntityId($ticket->getId())
            ->generateUrl();

        /** @noinspection HtmlUnknownTarget */
        return sprintf('<a href="%s" target="_blank">Открыть карточку тикета #%d ↗</a>', $url, $ticket->getId());
    }
}
