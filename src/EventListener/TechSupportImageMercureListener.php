<?php

namespace App\EventListener;

use App\Entity\Extra\MultipleImage;
use App\Entity\TechSupport\TechSupport;
use App\Entity\Trait\Readable\G;
use App\Service\Extra\MercurePublisher;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsEntityListener;
use Doctrine\ORM\Events;

/**
 * Live-уведомление о том, что у тикета ТП поменялся набор фото — фото могут
 * отсоединяться от заявки (PATCH .../{id} с images, DELETE
 * /multiple-images/{id} модерацией) независимо от смены статуса, а
 * ApiPatchTechSupportController публикует Mercure-событие только на смену
 * статуса (см. §11 API_REFERENCE.md — "title/description/priority/reason/
 * images PATCHed alone do *not* emit an event"). Слушаем сам MultipleImage,
 * а не конкретный контроллер — так событие ловит ЛЮБОЙ путь изменения фото
 * (upload, PATCH-синк, админ-модерация), а не только один из них.
 *
 * Отдельный тип события ('images_updated'), не 'updated' — тот уже
 * задокументирован как "именно смена статуса"; смешивать с фото значило бы
 * ломать это допущение для фронта, которому сейчас не нужно бы было
 * различать, что именно изменилось.
 *
 * preRemove + postRemove, а не просто postRemove — тот же приём, что в
 * ReviewListener: после физического удаления сущность теряет связи
 * (getTechSupport() вернёт null), поэтому связь запоминаем ДО удаления.
 */
#[AsEntityListener(event: Events::postPersist, entity: MultipleImage::class)]
#[AsEntityListener(event: Events::preRemove, entity: MultipleImage::class)]
#[AsEntityListener(event: Events::postRemove, entity: MultipleImage::class)]
class TechSupportImageMercureListener
{
    private ?TechSupport $removedTechSupport = null;

    public function __construct(private readonly MercurePublisher $publisher) {}

    public function postPersist(MultipleImage $image): void
    {
        $this->publishIfTechSupport($image->getTechSupport());
    }

    public function preRemove(MultipleImage $image): void
    {
        $this->removedTechSupport = $image->getTechSupport();
    }

    public function postRemove(): void
    {
        if ($this->removedTechSupport) {
            $this->publishIfTechSupport($this->removedTechSupport);
            $this->removedTechSupport = null;
        }
    }

    private function publishIfTechSupport(?TechSupport $techSupport): void
    {
        if (!$techSupport) return;

        $this->publisher->publish("tech-support:{$techSupport->getId()}", 'images_updated', $techSupport, G::OPS_TECH_SUPPORT);
    }
}
