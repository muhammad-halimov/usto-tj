<?php

namespace App\Service\Extra;

use Psr\Log\LoggerInterface;
use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Mercure\Update;
use Symfony\Component\Serializer\SerializerInterface;
use Throwable;

/**
 * Общая публикация событий в Mercure-хаб.
 *
 * Раньше сериализация сущности + сборка конверта {"type","data"} + publish()
 * были продублированы в ChatMessageListener. Теперь оба места (чат и
 * техподдержка) используют один и тот же сервис — при изменении формата
 * события правится в одном месте.
 *
 * private: true всегда — топики защищены подписным JWT, выдаваемым отдельным
 * эндпоинтом (см. ApiGetChatSubscribeTokenController /
 * ApiGetTechSupportSubscribeTokenController).
 *
 * ОШИБКИ ГЛУШАТСЯ НАМЕРЕННО: publish() вызывается из postPersist/postUpdate
 * Doctrine-листенеров ВНУТРИ flush(). Если Mercure-хаб недоступен (сеть,
 * хаб не поднят, невалидный ключ и т.д.), исключение оттуда раньше ронял
 * всю транзакцию — сообщение/сущность не сохранялись в БД только из-за
 * того, что не получилось отправить live-уведомление. Это неверный
 * приоритет: сохранить данные важнее, чем доставить push. Поэтому здесь
 * ошибка публикации только логируется — фронтенд просто не получит
 * live-обновление и узнает о новых данных при следующем обычном запросе.
 */
readonly class MercurePublisher
{
    public function __construct(
        private HubInterface        $hub,
        private SerializerInterface $serializer,
        private LoggerInterface     $logger,
    ) {}

    /**
     * Сериализует сущность нужными группами и публикует конверт
     * {"type": ..., "data": {...сущность...}} в приватный топик.
     *
     * @param string   $topic  Например "chat:42" или "tech-support:7"
     * @param string   $type   "created" | "updated" | "deleted"
     * @param string[] $groups Группы сериализации сущности (те же, что у GET-эндпоинта)
     */
    public function publish(string $topic, string $type, object $entity, array $groups): void
    {
        try {
            $json = $this->serializer->serialize($entity, 'json', [
                'groups'           => $groups,
                'skip_null_values' => false,
            ]);
        } catch (Throwable $e) {
            $this->logFailure($e, $topic, $type);
            return;
        }

        $this->publishRaw($topic, $type, json_decode($json, true));
    }

    /**
     * Публикует готовый payload напрямую — без сериализации сущности.
     * Нужен для событий вроде "deleted", где сущности уже нет в БД
     * и сериализовать нечего (см. ChatMessageListener::postRemove()).
     */
    public function publishRaw(string $topic, string $type, array $data): void
    {
        try {
            $this->hub->publish(new Update(
                topics: $topic,
                data: json_encode(['type' => $type, 'data' => $data]),
                private: true,
            ));
        } catch (Throwable $e) {
            $this->logFailure($e, $topic, $type);
        }
    }

    private function logFailure(Throwable $e, string $topic, string $type): void
    {
        $this->logger->error('Mercure publish failed: {message}', [
            'message'   => $e->getMessage(),
            'topic'     => $topic,
            'type'      => $type,
            'exception' => $e,
        ]);
    }
}
