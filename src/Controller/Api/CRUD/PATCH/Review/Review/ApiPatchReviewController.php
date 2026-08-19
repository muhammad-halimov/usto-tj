<?php

namespace App\Controller\Api\CRUD\PATCH\Review\Review;

use App\ApiResource\AppMessages;
use App\Controller\Api\CRUD\Abstract\AbstractApiPatchController;
use App\Dto\Review\ReviewPatchInput;
use App\Entity\Extra\MultipleImage;
use App\Entity\Review\Review;
use App\Entity\Trait\Readable\G;
use App\Entity\User;
use App\Service\Extra\LocalizationService;
use Symfony\Component\HttpFoundation\JsonResponse;

class ApiPatchReviewController extends AbstractApiPatchController
{
    public function __construct(private readonly LocalizationService $localizationService) {}

    protected function getEntityClass(): string { return Review::class; }

    protected function getInputClass(): string { return ReviewPatchInput::class; }

    protected function getNotFoundError(): string { return AppMessages::REVIEW_NOT_FOUND; }

    protected function checkOwnership(object $entity, ?User $bearer): ?JsonResponse
    {
        /** @var Review $entity */
        if ($bearer !== $entity->getClient() && $bearer !== $entity->getMaster())
            return $this->errorJson(AppMessages::OWNERSHIP_MISMATCH);

        // Отзыв можно редактировать только в течение 24 часов после создания —
        // дальше он считается "устоявшимся" (на него уже могли ссылаться в
        // рейтинге/споре), поэтому правка закрывается насовсем, не только
        // владением проверяем. Тот же хелпер, что у ChatMessage/
        // TechSupportMessage (см. AbstractApiHelperController::isPastEditWindow),
        // но у отзыва окно дольше (24ч, дефолт хелпера) — сообщения ТП/чата
        // правятся 15 минут (MESSAGE_EDIT_WINDOW), отзыв — не то же самое,
        // что реплика в переписке, поэтому и окно другое.
        if ($this->isPastEditWindow($entity->getCreatedAt()))
            return $this->errorJson(AppMessages::EDIT_WINDOW_EXPIRED);

        return null;
    }

    protected function applyChanges(object $entity, User $bearer, object $dto): ?JsonResponse
    {
        /** @var Review $entity */
        /** @var ReviewPatchInput $dto */
        if ($dto->rating < 1 || $dto->rating > 5) return $this->errorJson(AppMessages::INVALID_RATING);

        // !== null — раньше отсутствие description в теле запроса всё равно
        // стирало его (дефолт DTO — null), теперь как у ChatMessage/
        // TechSupportMessage: поле не прислали — не трогаем.
        if ($dto->description !== null) {
            // Не даём стереть текст и вписать полностью другой в рамках
            // "правки" — тот же смысл, что и у ChatMessage/TechSupportMessage:
            // иначе 24-часовой лимит выше ничего не значил бы.
            if ($this->isEditTooDifferent($entity->getDescription(), $dto->description))
                return $this->errorJson(AppMessages::EDIT_TOO_DIFFERENT);

            $entity->setDescription($dto->description);
        }

        $entity->setRating($dto->rating);

        // !== null — раньше это выполнялось БЕЗУСЛОВНО на каждый PATCH: DTO
        // дефолтил images в [], так что правка одного rating/description без
        // единого упоминания images стирала все фото отзыва. Теперь как и
        // везде — поле не прислали, фото не трогаем.
        if ($dto->images !== null) {
            foreach ($entity->getImages() as $img) {
                $entity->removeImage($img);
                $this->entityManager->remove($img);
            }

            foreach ($dto->images as $image) {
                if (!empty($image->image) && $image->image !== 'string') {
                    $newImage = (new MultipleImage())->setImage($image->image);
                    $this->persist($newImage);
                    $entity->addImage($newImage);
                }
            }
        }

        return null;
    }

    protected function setSerializationGroups(): array
    {
        return G::OPS_REVIEWS;
    }

    protected function afterFetch(object|array $entity, ?User $user): void
    {
        /** @var Review $entity */
        if ($entity->getMaster()) $this->localizationService->localizeUser($entity->getMaster(), $this->getLocale());
        if ($entity->getClient()) $this->localizationService->localizeUser($entity->getClient(), $this->getLocale());
        if ($entity->getTicket()) $this->localizationService->localizeTicket($entity->getTicket(), $this->getLocale());
    }
}
