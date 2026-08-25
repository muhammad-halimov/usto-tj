<?php

namespace App\Service\Extra;

use Psr\Cache\InvalidArgumentException;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

/**
 * Тонкая обёртка над Symfony CacheInterface — единственный потребитель
 * сейчас это OAuth anti-CSRF state (AbstractOAuthService::
 * generateOAuthRedirectUri()/handleCode() и LinkOAuthProviderController::
 * resolveProviderId()), но сам сервис общего назначения — просто key/value
 * с TTL, ничего специфичного для OAuth здесь нет.
 */
class StateStorageService
{
    /** 10 минут — сколько живёт state с момента /url до момента /callback. */
    private const int TTL = 600;

    public function __construct(private readonly CacheInterface $cache) {}

    /**
     * delete() перед записью — подстраховка от коллизии с уже
     * протухающим/просроченным элементом под тем же ключом (крайне
     * маловероятно при 16 случайных байтах state, но дёшево на всякий
     * случай).
     *
     * @throws InvalidArgumentException
     */
    public function save(string $key, string $value): void
    {
        $this->cache->delete($key);
        $this->cache->get($key, function (ItemInterface $item) use ($value): string {
            $item->expiresAfter(self::TTL);
            return $value;
        });
    }

    /**
     * hasItem() сначала, чтобы отличить "ключа нет/протух" (null) от
     * "есть, но значение — пустая строка" — иначе cache->get() с
     * колбэком по умолчанию сам бы СОЗДАЛ отсутствующий элемент как
     * побочный эффект чтения (поведение Symfony Cache "compute on miss"),
     * что здесь недопустимо: get() используется как одноразовая ПРОВЕРКА
     * существования state, а не как способ его создать.
     *
     * @throws InvalidArgumentException
     */
    public function get(string $key): ?string
    {
        if (!$this->cache->hasItem($key)) return null;

        return $this->cache->get($key, fn(ItemInterface $item): string => '');
    }

    /** @throws InvalidArgumentException */
    public function delete(string $key): void
    {
        $this->cache->delete($key);
    }
}
