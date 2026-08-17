<?php

namespace App\Controller\Api\CRUD\POST\TechSupport\Message;

use App\ApiResource\AppMessages;
use App\Controller\Api\CRUD\Abstract\AbstractApiPostController;
use App\Dto\TechSupport\TechSupportMessagePostInput;
use App\Entity\TechSupport\TechSupport;
use App\Entity\TechSupport\TechSupportMessage;
use App\Entity\Trait\Readable\G;
use App\Entity\User;
use App\Service\Extra\LocalizationService;
use Symfony\Component\HttpFoundation\JsonResponse;

class ApiPostTechSupportMessageController extends AbstractApiPostController
{
    public function __construct(private readonly LocalizationService $localizationService) {}

    protected function getInputClass(): string { return TechSupportMessagePostInput::class; }

    protected function setSerializationGroups(): array { return G::OPS_TECH_MSGS; }

    protected function afterFetch(object|array $entity, ?User $user): void
    {
        /** @var TechSupportMessage $entity */
        if ($entity->getAuthor()) $this->localizationService->localizeUser($entity->getAuthor(), $this->getLocale());
    }

    protected function handle(?User $bearer, object $dto): object
    {
        /** @var TechSupportMessagePostInput $dto */
        // === null (не !$dto->description) — как в ApiPostChatMessageController:
        // фото прикрепляется ПОСЛЕ создания через отдельный upload-images,
        // поэтому description: "" (пустая строка, просто фото без текста)
        // обязан проходить, отсекаем только реально отсутствующее поле.
        if ($dto->description === null) return $this->errorJson(AppMessages::EMPTY_TEXT);
        if (!$dto->techSupport) return $this->errorJson(AppMessages::MISSING_REQUIRED_FIELDS);

        $techSupport = $dto->techSupport;

        if ($error = $this->checkOwnership($techSupport, $bearer)) return $error;

        $techSupportMessage = (new TechSupportMessage())
            ->setDescription($dto->description)
            ->setTechSupport($techSupport)
            ->setAuthor($bearer);

        $this->persist($techSupportMessage);

        $techSupport->addTechSupportMessage($techSupportMessage);

        $this->flush();

        return $techSupportMessage;
    }

    protected function checkOwnership(object $entity, ?User $bearer): ?JsonResponse
    {
        /** @var TechSupport $entity */
        // Любой ROLE_ADMIN (и ROLE_SUPER_ADMIN через User::getRoles()) может
        // писать в любой тикет, не только назначенный лично на него.
        $isAdmin = in_array('ROLE_ADMIN', $bearer->getRoles(), true);

        if (!$isAdmin && $entity->getAdministrant() !== $bearer && $entity->getAuthor() !== $bearer)
            return $this->errorJson(AppMessages::OWNERSHIP_MISMATCH);

        // Заблокированный тикет (TechSupport::STATUS_BANNED): взаимодействовать
        // может только админ, автор — уже нет.
        if (!$isAdmin && $entity->getStatus() === TechSupport::STATUS_BANNED)
            return $this->errorJson(AppMessages::ACCESS_DENIED);

        return null;
    }
}
