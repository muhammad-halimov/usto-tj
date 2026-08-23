<?php

namespace App\Doctrine;

use ApiPlatform\Doctrine\Orm\Extension\QueryCollectionExtensionInterface;
use ApiPlatform\Doctrine\Orm\Util\QueryNameGeneratorInterface;
use ApiPlatform\Metadata\Operation;
use App\Entity\Review\Review;
use Doctrine\ORM\QueryBuilder;

/**
 * Doctrine ORM extension: скрывает из коллекции GET /reviews отзывы, где
 * ЛЮБАЯ из двух сторон (master или client) сейчас деактивирована или не
 * подтверждена — то же условие (active = true AND approved = true), что
 * ApprovedTicketExtension/UserVisibilityExtension применяют к публикатору.
 *
 * Изначально тут проверялся только "субъект" отзыва — сторона, зависящая
 * от Review::$type (master при type='master', client при type='client').
 * Это было недостаточно: master и client сериализуются в ответе ПОЛНОСТЬЮ
 * (не IRI-ссылкой), поэтому даже когда отзыв оставался видимым по субъекту,
 * вторая сторона (не субъект) всё равно утекала в ответ целиком со своими
 * данными, включая явный active=false/approved=false. Проверяем обе стороны,
 * чтобы деактивация любого из участников отзыва скрывала его целиком.
 *
 * Если сторона не заполнена (master/client IS NULL) — не скрываем по ней:
 * тот же null-safe подход, что уже применяется в FavoriteVisibilityExtension.
 *
 * Не применяется к:
 *   GET /api/reviews/me   — кастомный контроллер (ApiGetMyReviewsController),
 *                            репозиторий вызывается напрямую, этот extension
 *                            не участвует в его pipeline.
 *   GET /api/reviews/{id} — обрабатывается отдельно в
 *                            ReviewLocalizationProvider::provide(), где то же
 *                            условие продублировано для одиночного отзыва.
 */
final class ReviewVisibilityExtension implements QueryCollectionExtensionInterface
{
    public function applyToCollection(
        QueryBuilder                $queryBuilder,
        QueryNameGeneratorInterface $queryNameGenerator,
        string                      $resourceClass,
        ?Operation                  $operation = null,
        array                       $context   = [],
    ): void {
        // Extension применяется ко всем ресурсам API Platform — фильтруем,
        // чтобы затрагивать только коллекции Review, не трогая остальные.
        if ($resourceClass !== Review::class) {
            return;
        }

        $alias = $queryBuilder->getRootAliases()[0];

        $masterAlias = $queryNameGenerator->generateJoinAlias('master');
        $clientAlias = $queryNameGenerator->generateJoinAlias('client');

        // LEFT JOIN (не INNER), потому что у отзыва заполнено только ОДНО
        // из полей (master ИЛИ client) в зависимости от type — но проверяем
        // ОБЕ стороны, а не только ту, что соответствует типу.
        $queryBuilder
            ->leftJoin("$alias.master", $masterAlias)
            ->leftJoin("$alias.client", $clientAlias)
            ->andWhere(
                "($alias.master IS NULL OR ($masterAlias.active = true AND $masterAlias.approved = true))
                AND
                ($alias.client IS NULL OR ($clientAlias.active = true AND $clientAlias.approved = true))"
            );
    }
}
