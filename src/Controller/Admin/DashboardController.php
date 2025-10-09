<?php

namespace App\Controller\Admin;

use App\Controller\Admin\User\UserCrudController;
use App\Entity\Chat\Chat;
use App\Entity\Gallery\Gallery;
use App\Entity\Geography\City;
use App\Entity\Geography\District;
use App\Entity\Geography\Geography;
use App\Entity\Service\Category;
use App\Entity\Service\Unit;
use App\Entity\Ticket\Ticket;
use App\Entity\User;
use App\Entity\User\Review;
use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminDashboard;
use EasyCorp\Bundle\EasyAdminBundle\Config\Dashboard;
use EasyCorp\Bundle\EasyAdminBundle\Config\MenuItem;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractDashboardController;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use Symfony\Component\HttpFoundation\Response;

#[AdminDashboard(routePath: '/admin', routeName: 'admin')]
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
            ->setController(UserCrudController::class)
            ->generateUrl());
    }

    public function configureDashboard(): Dashboard
    {
        return Dashboard::new()
            ->setTitle('USTO.TJ');
    }

    public function configureMenuItems(): iterable
    {
        yield MenuItem::section('Мастера и клиенты');
            yield MenuItem::linkToCrud('Галерея работ', 'fas fa-images', Gallery::class);
            yield MenuItem::linkToCrud('Объявление / Услуги', 'fas fa-ticket', Ticket::class);

        yield MenuItem::section('Пользователи и группы');
            yield MenuItem::linkToCrud('Пользователи', 'fas fa-users', User::class);
            yield MenuItem::linkToCrud('Чаты и сообщения', 'fas fa-comments', Chat::class);
            yield MenuItem::linkToCrud('Отзывы', 'fas fa-star', Review::class);

        yield MenuItem::section('Доп. настройки');
            yield MenuItem::subMenu('География', 'fas fa-location-dot')->setSubItems([
                MenuItem::linkToCrud('Локация', 'fas fa-map-pin', Geography::class),
                MenuItem::linkToCrud('Город', 'fas fa-city', City::class),
                MenuItem::linkToCrud('Район', 'fas fa-building', District::class),
            ]);
            yield MenuItem::linkToCrud('Категории работ', 'fas fa-list', Category::class);
            yield MenuItem::linkToCrud('Ед. измерения', 'fas fa-gauge', Unit::class);
            yield MenuItem::linkToUrl('API','fas fa-link', '/api')
                ->setLinkTarget('_blank');
    }
}
