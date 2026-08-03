<?php

namespace App\Service\Extra;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Утилита для равномерного распределения нагрузки между администраторами.
 *
 * Не привязана к конкретной сущности (TechSupport, TicketApproval и т.д.) —
 * работает с любой сущностью, у которой есть setAdministrant(). Репозиторий
 * для подсчёта нагрузки получается автоматически по классу самой сущности —
 * вызывающему коду не нужно передавать его отдельно.
 *
 * Критерии "активности" (что считается нагрузкой) передаются как готовый
 * массив условий для Repository::count() — это может быть строковый статус
 * (['status' => ['new', 'in_progress']]) или булево поле (['approved' => false]),
 * в зависимости от того, как устроена конкретная сущность.
 */
readonly class AdminLoadBalancerService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {}

    /**
     * Находит наименее загруженного администратора и назначает его сущности.
     *
     * @param object $entity          Сущность, которой назначаем администратора (должна иметь setAdministrant())
     * @param array  $activeCriteria  Критерии "активной" (создающей нагрузку) записи, без ключа 'administrant'
     */
    public function setLeastLoadedAdmin(object $entity, array $activeCriteria): void
    {
        $admins = $this->entityManager->getRepository(User::class)->findAllByRole('ROLE_ADMIN');

        if (empty($admins)) return;

        $repository = $this->entityManager->getRepository($entity::class);

        $minCount = PHP_INT_MAX;
        $leastLoadedAdmin = null;

        foreach ($admins as $admin) {
            $count = $repository->count(['administrant' => $admin, ...$activeCriteria]);

            if ($count < $minCount) {
                $minCount = $count;
                $leastLoadedAdmin = $admin;
            }
        }

        if ($leastLoadedAdmin !== null) $entity->setAdministrant($leastLoadedAdmin);
    }
}
