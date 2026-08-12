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
     * Таблица переходов, доступных АВТОРУ самостоятельно (без админа).
     *
     * Формат: [текущий статус => [список статусов, куда автор может перевести сам]]
     *
     *   resolved → renewed  (автор не согласен с решением — переоткрывает тикет)
     *   closed   → renewed  (тикет закрыли из-за отсутствия ответа/нерешённости —
     *                         автор возвращается и продолжает с тем же тикетом,
     *                         вместо того чтобы заводить новый с нуля)
     *
     * ROLE_ADMIN (включая ROLE_SUPER_ADMIN — см. User::getRoles()) переводит
     * тикет в ЛЮБОЙ статус из ЛЮБОГО, без ограничений этой таблицы — это
     * полный оverride для модерации/исправления ошибок, в т.ч. разбан
     * (banned → что угодно). Раньше banned был терминальным даже для админа;
     * это ограничение снято намеренно — в остальных местах (сообщения, фото)
     * админ и так уже обходит блокировку banned, самому статусу тоже незачем
     * быть исключением.
     */
    private const array AUTHOR_TRANSITIONS = [
        'resolved' => ['renewed'],
        'closed'   => ['renewed'],
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

        // Админ — без ограничений, любой статус из любого (см. AUTHOR_TRANSITIONS
        // выше). Это касается и разбана: banned → что угодно тоже разрешено.
        if ($isAdmin) {
            $entity->setStatus($newStatus);
            return null;
        }

        // Автор — только узкий список самостоятельных переходов из AUTHOR_TRANSITIONS.
        $isAuthor = $entity->getAuthor() === $bearer;
        $allowed  = self::AUTHOR_TRANSITIONS[$entity->getStatus()] ?? [];

        if (!$isAuthor || !in_array($newStatus, $allowed, true))
            return $this->errorJson(AppMessages::EXTRA_DENIED);

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
