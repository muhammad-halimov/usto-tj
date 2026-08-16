<?php

namespace App\Controller\Admin;

use App\Controller\Admin\Appeal\Appeal\AppealCrudController;
use App\Controller\Admin\Appeal\AppealReason\AppealReasonCrudController;
use App\Controller\Admin\Appeal\AppealTypes\AppealChatCrudController;
use App\Controller\Admin\Appeal\AppealTypes\AppealReviewCrudController;
use App\Controller\Admin\Appeal\AppealTypes\AppealTicketCrudController;
use App\Controller\Admin\Appeal\AppealTypes\AppealUserCrudController;
use App\Controller\Admin\Chat\Chat\ChatCrudController;
use App\Controller\Admin\Extra\EntityRevisionCrudController;
use App\Controller\Admin\Gallery\GalleryCrudController;
use App\Controller\Admin\Geography\City\CityCrudController;
use App\Controller\Admin\Geography\District\DistrictCrudController;
use App\Controller\Admin\Geography\Province\ProvinceCrudController;
use App\Controller\Admin\Legal\LegalCrudController;
use App\Controller\Admin\Review\ReviewCrudController;
use App\Controller\Admin\TechSupport\TechSupport\TechSupportCrudController;
use App\Controller\Admin\TechSupport\TicketApproval\TicketApprovalCrudController;
use App\Controller\Admin\Ticket\Category\CategoryCrudController;
use App\Controller\Admin\Ticket\Ticket\TicketCrudController;
use App\Controller\Admin\Ticket\Unit\UnitCrudController;
use App\Controller\Admin\User\Occupation\OccupationCrudController;
use App\Controller\Admin\User\User\UserCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminDashboard;
use EasyCorp\Bundle\EasyAdminBundle\Config\Dashboard;
use EasyCorp\Bundle\EasyAdminBundle\Config\MenuItem;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractDashboardController;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use Symfony\Component\HttpFoundation\Response;

#[AdminDashboard(routePath: '/', routeName: 'admin')]
class DashboardController extends AbstractDashboardController
{
    /**
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    public function index(): Response
    {
        return $this
            ->redirect(url: $this->container
                ->get(AdminUrlGenerator::class)
                ->setController(TechSupportCrudController::class)
                ->generateUrl());
    }

    public function configureDashboard(): Dashboard
    {
        return Dashboard::new()
            ->setTitle('USTOYOB.TJ');
    }

    public function configureMenuItems(): iterable
    {
        yield MenuItem::section('Мастера и клиенты');
        yield MenuItem::linkTo(GalleryCrudController::class, 'Галерея работ', 'fas fa-images');
        yield MenuItem::linkTo(TicketCrudController::class, 'Объявление / Услуги', 'fas fa-ticket');

        yield MenuItem::section('Пользователи и группы');
        yield MenuItem::linkTo(UserCrudController::class, 'Пользователи', 'fas fa-users');
        yield MenuItem::linkTo(ChatCrudController::class, 'Чаты и сообщения', 'fas fa-comments');
        yield MenuItem::linkTo(ReviewCrudController::class, 'Отзывы', 'fas fa-star');
        yield MenuItem::subMenu('Тех. поддержка', 'fas fa-headset')->setSubItems([
            MenuItem::linkTo(TechSupportCrudController::class, 'Тех. поддержка', 'fas fa-headset'),
            MenuItem::linkTo(TicketApprovalCrudController::class, 'Подтверждение объявлений/услуг', 'fas fa-check-double'),
            MenuItem::linkTo(EntityRevisionCrudController::class, 'Аудит изменений', 'fas fa-clock-rotate-left'),
        ]);
        yield MenuItem::subMenu('Жалобы', 'fas fa-triangle-exclamation')->setSubItems([
            MenuItem::linkTo(AppealCrudController::class, 'Все жалобы', 'fas fa-list'),
            MenuItem::linkTo(AppealChatCrudController::class, 'На чат', 'fas fa-comments'),
            MenuItem::linkTo(AppealTicketCrudController::class, 'На объявление/услугу', 'fas fa-ticket'),
            MenuItem::linkTo(AppealReviewCrudController::class, 'На отзыв', 'fas fa-star'),
            MenuItem::linkTo(AppealUserCrudController::class, 'На пользователя', 'fas fa-user-xmark'),
            MenuItem::linkTo(AppealReasonCrudController::class, 'Причины жалоб', 'fas fa-tags'),
        ]);
        yield MenuItem::section('Доп. настройки');
        yield MenuItem::subMenu('География', 'fas fa-location-dot')->setSubItems([
            MenuItem::linkTo(CityCrudController::class, 'Город', 'fas fa-city'),
            MenuItem::linkTo(DistrictCrudController::class, 'Район', 'fas fa-building'),
            MenuItem::linkTo(ProvinceCrudController::class, 'Область', 'fas fa-map-pin'),
        ]);
        yield MenuItem::subMenu('Категории', 'fas fa-layer-group')->setSubItems([
            MenuItem::linkTo(CategoryCrudController::class, 'Категории работ', 'fas fa-briefcase'),
            MenuItem::linkTo(OccupationCrudController::class, 'Специальности / Подкатегории', 'fas fa-user-doctor'),
        ]);
        yield MenuItem::linkTo(UnitCrudController::class, 'Ед. измерения', 'fas fa-gauge');
        yield MenuItem::linkTo(LegalCrudController::class, 'Регуляции', 'fas fa-lock');
        yield MenuItem::linkToUrl('API','fas fa-link', '/api')
            ->setLinkTarget('_blank');
    }
}
