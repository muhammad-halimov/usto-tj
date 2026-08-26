<?php

namespace App\Exception;

use RuntimeException;

/**
 * БАГФИКС (26.08.2026): единый способ кидать ошибку из каталога
 * AppMessages ИЗ ЛЮБОГО МЕСТА — сервиса, сущности, Doctrine-листенера —
 * не только из кастомного контроллера, где для этого уже был
 * AbstractApiHelperController::errorJson().
 *
 * До этого фикса весь код вне контроллеров (все OAuth-сервисы,
 * AccessService — центральная точка проверки доступа буквально ВСЕХ
 * защищённых эндпоинтов, ExtractIriService и т.д.) кидал
 * `throw new BadRequestHttpException(AppMessages::get(CODE)->message)` —
 * это ДОЛЕТАЕТ ДО КЛИЕНТА БЕЗ CODE ВООБЩЕ: API Platform рендерит голое
 * исключение как generic Problem+JSON `{title, detail, status, type}`,
 * а не как `{code, message}`, который документирован в API_REFERENCE.md
 * и ожидается фронтендом. Реально проверено: именно так выглядел тот
 * самый Instagram-баг с самого начала — `{"title":"An error occurred",
 * "detail":"Мубодилаи код бо провайдер ноком шуд","status":400,
 * "type":"/errors/400"}`, без единого поля code.
 *
 * Кидайте это исключение вместо голого BadRequestHttpException/
 * AccessDeniedHttpException/NotFoundHttpException и т.п. — оно
 * перехватывается AppMessageExceptionListener и превращается в тот же
 * {code, message} + правильный HTTP-статус, что уже отдаёт errorJson(),
 * независимо от того, что за класс исключения был бы нужен раньше —
 * http-статус в любом случае берётся из самого AppMessages::REGISTRY,
 * а не из типа PHP-исключения.
 *
 * @see \App\EventListener\AppMessageExceptionListener
 */
class AppMessageException extends RuntimeException
{
    /**
     * @param string $appCode Один из кодов AppMessages::* (например
     *                        AppMessages::OAUTH_INVALID_STATE) — НЕ
     *                        локализованный текст, локаль резолвится в
     *                        листенере в момент рендера ответа.
     * @param string|null $extra Необязательный доп. текст, который
     *                            листенер допишет к локализованному
     *                            сообщению через ' ' (например список
     *                            допустимых значений) — раньше это
     *                            делалось конкатенацией прямо в throw,
     *                            что и приводило к описанной выше потере
     *                            code.
     */
    public function __construct(
        public readonly string  $appCode,
        public readonly ?string $extra = null,
    ) {
        parent::__construct($appCode);
    }
}
