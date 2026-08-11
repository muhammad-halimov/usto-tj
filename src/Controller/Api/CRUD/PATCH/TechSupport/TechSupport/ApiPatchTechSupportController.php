<?php

namespace App\Controller\Api\CRUD\PATCH\TechSupport\TechSupport;

use App\ApiResource\AppMessages;
use App\Controller\Api\CRUD\Abstract\AbstractApiPatchController;
use App\Dto\TechSupport\TechSupportPatchInput;
use App\Entity\TechSupport\TechSupport;
use App\Entity\Trait\Readable\G;
use App\Entity\User;
use App\Repository\TechSupport\TechSupportRepository;
use App\Service\Extra\LocalizationService;
use Symfony\Component\HttpFoundation\JsonResponse;

class ApiPatchTechSupportController extends AbstractApiPatchController
{
    public function __construct(
        private readonly TechSupportRepository $techSupportRepository,
        private readonly LocalizationService $localizationService,
    ) {}

    protected function setSerializationGroups(): array { return G::OPS_TECH_SUPPORT_POST; }

    protected function getUserGrade(): string { return 'double'; }

    protected function getNotFoundError(): string { return AppMessages::TECH_SUPPORT_NOT_FOUND; }

    protected function getInputClass(): string { return TechSupportPatchInput::class; }

    protected function getEntityById(int $id): ?object
    {
        return $this->techSupportRepository->find($id);
    }

    protected function checkOwnership(object $entity, ?User $bearer): ?JsonResponse
    {
        /** @var TechSupport $entity */
        // Любой ROLE_ADMIN может работать с любым тикетом, не только со
        // "своим" назначенным — тот же паттерн, что в ApiGetTechSupportController.
        // ROLE_SUPER_ADMIN проходит автоматически (см. User::getRoles()).
        $isAdmin = in_array('ROLE_ADMIN', $bearer->getRoles(), true);

        if (!$isAdmin && $entity->getAuthor() !== $bearer && $entity->getAdministrant() !== $bearer)
            return $this->errorJson(AppMessages::EXTRA_DENIED);

        return null;
    }

    /**
     * Таблица допустимых переходов статуса (машина состояний).
     *
     * Формат: [текущий статус => [новый статус => 'кто может перевести']]
     *   'admin'  — только ROLE_ADMIN
     *   'author' — только автор тикета (пользователь, который его создал)
     *
     * Переходы:
     *   new         → in_progress (админ берёт в работу)
     *   new         → closed      (админ закрывает без ответа)
     *   new         → banned      (админ блокирует тикет/автора)
     *   renewed     → in_progress (админ взял повторно открытый тикет)
     *   renewed     → closed      (админ закрывает)
     *   renewed     → banned      (админ блокирует)
     *   in_progress → resolved    (админ отмечает как решённое)
     *   in_progress → closed      (админ закрывает)
     *   in_progress → banned      (админ блокирует)
     *   resolved    → renewed     (автор не согласен — переоткрывает тикет)
     *   resolved    → closed      (админ закрывает)
     *   resolved    → banned      (админ блокирует)
     *   closed      → banned      (админ блокирует уже закрытый тикет)
     *   banned      → (нет, терминальный статус — даже для админа; заблокировано намеренно)
     */
    private const array TRANSITIONS = [
        'new'         => ['in_progress' => 'admin', 'closed' => 'admin', 'banned' => 'admin'],
        'renewed'     => ['in_progress' => 'admin', 'closed' => 'admin', 'banned' => 'admin'],
        'in_progress' => ['resolved'    => 'admin', 'closed' => 'admin', 'banned' => 'admin'],
        'resolved'    => ['renewed'     => 'author', 'closed' => 'admin', 'banned' => 'admin'],
        'closed'      => ['banned'      => 'admin'],
    ];

    /**
     * Автор тикета может патчить только статус (по правилам TRANSITIONS ниже).
     * Админ может то же самое, плюс title/reason/priority/description/images —
     * унаследованные от TechSupportInput поля, общие с POST (см. DTO).
     */
    protected function applyChanges(object $entity, User $bearer, object $dto): ?JsonResponse
    {
        /** @var TechSupport $entity */
        /** @var TechSupportPatchInput $dto */
        $isAdmin = in_array('ROLE_ADMIN', $bearer->getRoles(), true);

        // Статус теперь необязателен в теле запроса — PATCH умеет менять
        // и другие поля отдельно от смены статуса.
        if ($dto->status !== null) {
            if ($error = $this->applyStatusTransition($entity, $bearer, $isAdmin, $dto->status)) {
                return $error;
            }
        }

        if ($isAdmin) {
            if ($dto->reason !== null && !in_array($dto->reason->getApplicableTo(), ['support', 'overall'], true)) {
                return $this->errorJson(AppMessages::WRONG_SUPPORT_REASON);
            }

            if ($dto->title !== null)       $entity->setTitle($dto->title);
            if ($dto->description !== null) $entity->setDescription($dto->description);
            if ($dto->priority !== null)    $entity->setPriority($dto->priority);
            if ($dto->reason !== null)      $entity->setReason($dto->reason);

            if (!empty($dto->images)) {
                $this->syncImages($entity, $dto->images, $bearer);
            }
        }

        return null;
    }

    private function applyStatusTransition(TechSupport $entity, User $bearer, bool $isAdmin, string $newStatus): ?JsonResponse
    {
        // Сначала проверяем, что новый статус вообще существует в системе.
        if (!in_array($newStatus, array_values(TechSupport::STATUSES), true))
            return $this->errorJson(AppMessages::WRONG_TECH_SUPPORT_STATUS);

        // Смотрим, допустимый ли переход из текущего статуса в новый.
        // Если текущего статуса нет в таблице (например, 'banned') — переходов нет.
        $allowed = self::TRANSITIONS[$entity->getStatus()] ?? [];

        if (!isset($allowed[$newStatus]))
            return $this->errorJson(AppMessages::WRONG_TECH_SUPPORT_STATUS);

        $isAuthor = $entity->getAuthor() === $bearer;

        // Проверяем, есть ли у пользователя право на этот конкретный переход.
        if ($allowed[$newStatus] === 'admin'  && !$isAdmin)  return $this->errorJson(AppMessages::EXTRA_DENIED);
        if ($allowed[$newStatus] === 'author' && !$isAuthor) return $this->errorJson(AppMessages::EXTRA_DENIED);

        $entity->setStatus($newStatus);

        return null;
    }

    protected function afterFetch(object|array $entity, ?User $user): void
    {
        /** @var TechSupport $entity */
        if ($entity->getAuthor()) $this->localizationService->localizeUser($entity->getAuthor(), $this->getLocale());
        if ($entity->getAdministrant()) $this->localizationService->localizeUser($entity->getAdministrant(), $this->getLocale());
        if ($entity->getReason()) $this->localizationService->localizeEntityFull($entity->getReason(), $this->getLocale());
    }
}
