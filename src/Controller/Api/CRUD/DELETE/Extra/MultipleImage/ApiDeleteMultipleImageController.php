<?php

namespace App\Controller\Api\CRUD\DELETE\Extra\MultipleImage;

use App\Controller\Api\CRUD\Abstract\AbstractApiDeleteController;
use App\Entity\Extra\MultipleImage;
use App\Entity\User;
use Symfony\Component\HttpFoundation\JsonResponse;

/**
 * Удаление фото ЛЮБОЙ версионируемой сущности — только ROLE_ADMIN/
 * ROLE_SUPER_ADMIN (getUserGrade='admin', см. AccessService — ROLE_SUPER_ADMIN
 * проходит любой grade, ROLE_ADMIN синтезируется в User::getRoles() тоже
 * для ROLE_SUPER_ADMIN, так что оба варианта покрыты одной проверкой).
 *
 * В отличие от syncImages() (там фото убирается как побочный эффект PATCH
 * владельца, и его может делать только автор/участник сущности), это
 * отдельная точка входа специально для модерации: админ убирает
 * неприемлемое фото у ЧУЖОГО тикета/отзыва/чата/техподдержки и т.д. по ID
 * самого фото, не редактируя (и тем более не владея) саму сущность.
 * checkOwnership не переопределён — базовый (AbstractApiHelperController)
 * всегда возвращает null, здесь это осознанно: единственная проверка прав —
 * роль, а не владение.
 *
 * Пишет тот же EntityRevision (entityType=multiple_image, action=deleted),
 * что и syncImages()/logImagesDeletion() — один audit trail независимо от
 * того, кто и через какую точку входа удалил фото. Вызывает
 * logImagesDeletion() с массивом из одного элемента — здесь всегда ровно
 * одно фото (удаление по ID конкретного фото), батч из нескольких за раз
 * возможен только через syncImages().
 */
class ApiDeleteMultipleImageController extends AbstractApiDeleteController
{
    protected function getEntityClass(): string
    {
        return MultipleImage::class;
    }

    protected function getUserGrade(): string
    {
        return 'admin';
    }

    protected function removeAndRespond(object $entity): JsonResponse
    {
        /** @var MultipleImage $entity */
        /** @var User $bearer */
        $bearer = $this->getCurrentUser();

        // Фото может ни к чему не относиться (осиротевшая запись) — тогда
        // просто нечего указать как parent, логируем без него не имеет
        // смысла (снапшот EntityRevision заточен под конкретного владельца),
        // само удаление всё равно происходит.
        $parent = $this->resolveImageParent($entity);
        if ($parent) {
            $this->logImagesDeletion([$entity], $parent, $bearer);
        }

        return parent::removeAndRespond($entity);
    }
}
