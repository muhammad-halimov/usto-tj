<?php

namespace App\EventListener;

use App\ApiResource\AppMessages;
use App\Exception\AppMessageException;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Перехватывает AppMessageException и превращает в {code, message} JSON
 * с правильным HTTP-статусом — тот же формат, что уже отдаёт
 * AbstractApiHelperController::errorJson() из кастомных контроллеров, но
 * теперь доступный и из сервисов/сущностей/Doctrine-листенеров, которые
 * кидают исключение, а не возвращают JsonResponse напрямую (см. докблок
 * AppMessageException — зачем это вообще понадобилось).
 *
 * Priority 10 — должен сработать раньше API Platform/Symfony дефолтного
 * error-рендерера (обычно priority ~ -128..0 у их слушателей), чтобы
 * именно наш JsonResponse ушёл клиенту, а не generic Problem+JSON.
 *
 * Локаль к этому моменту уже установлена: AppErrorLocaleListener висит на
 * kernel.request с priority 20 и отрабатывает в самом начале запроса,
 * задолго до того, как где-либо может быть выброшено исключение.
 */
#[AsEventListener(event: KernelEvents::EXCEPTION, priority: 10)]
class AppMessageExceptionListener
{
    public function __invoke(ExceptionEvent $event): void
    {
        $exception = $event->getThrowable();

        if (!$exception instanceof AppMessageException) {
            return;
        }

        $error   = AppMessages::get($exception->appCode);
        $message = $exception->extra !== null ? "{$error->message} {$exception->extra}" : $error->message;

        $event->setResponse(new JsonResponse(
            ['code' => $error->code, 'message' => $message],
            $error->http,
        ));
    }
}
