<?php

namespace App\Controller\Admin;

use App\Entity\Geography\UserServiceGeography;
use App\Entity\Geography\UserServiceGeographyCity;
use App\Entity\Geography\UserServiceGeographyDistrict;
use App\Entity\Service\Gallery\UserServiceGallery;
use App\Entity\Service\UserService;
use App\Entity\Service\UserServiceCategory;
use App\Entity\Service\UserServiceUnit;
use App\Entity\User;
use App\Entity\UserServiceReview;
use App\Entity\UserTicket;
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
        yield MenuItem::section('Мастера');
            yield MenuItem::subMenu('Услуги', 'fas fa-hammer')->setSubItems([
                MenuItem::linkToCrud('Услуги', 'fas fa-hammer', UserService::class),
                MenuItem::linkToCrud('Категории услуг', 'fas fa-list', UserServiceCategory::class),
                MenuItem::linkToCrud('Единица измерения', 'fas fa-gauge', UserServiceUnit::class),
            ]);
            yield MenuItem::linkToCrud('Галерея работ', 'fas fa-images', UserServiceGallery::class);
            yield MenuItem::linkToCrud('Отзывы работ', 'fas fa-star', UserServiceReview::class);

        yield MenuItem::section('Клиенты');
            yield MenuItem::linkToCrud('Объявления', 'fas fa-ticket', UserTicket::class);

        yield MenuItem::section('Пользователи и группы');
            yield MenuItem::linkToCrud('Пользователи', 'fas fa-users', User::class);

        yield MenuItem::section('Доп. настройки');
            yield MenuItem::subMenu('География', 'fas fa-location-dot')->setSubItems([
                MenuItem::linkToCrud('Локация', 'fas fa-map-pin', UserServiceGeography::class),
                MenuItem::linkToCrud('Город', 'fas fa-city', UserServiceGeographyCity::class),
                MenuItem::linkToCrud('Район', 'fas fa-building', UserServiceGeographyDistrict::class),
            ]);
            yield MenuItem::linkToUrl('API','fas fa-link', '/api')
                ->setLinkTarget('_blank');
    }
}
