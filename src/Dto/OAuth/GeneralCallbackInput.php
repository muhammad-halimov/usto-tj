<?php

namespace App\Dto\OAuth;

use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Input-DTO для POST /auth/{provider}/callback (Google/Facebook/
 * Instagram) — то, что фронтенд получил обратно от провайдера после
 * redirect с его страницы согласия: code + тот же state, что был выдан
 * на шаге /url.
 */
final class GeneralCallbackInput
{
    #[Groups([
        'google:write',
        'instagram:write',
        'facebook:write'
    ])]
    #[Assert\NotBlank]
    private string $code;

    #[Groups([
        'google:write',
        'instagram:write',
        'facebook:write'
    ])]
    #[Assert\NotBlank]
    public string $state;

    #[Groups([
        'google:write',
        'instagram:write',
        'facebook:write'
    ])]
    public ?string $role = null;

    public function getCode(): string
    {
        // Защита от двойного urlencode: если фронт (или редирект где-то
        // между провайдером и фронтом) уже раз закодировал code, второй
        // раз декодировать его тут безопасно — обычный "чистый" code без
        // спецсимволов urldecode() не тронет.
        return urldecode($this->code);
    }

    public function setCode(string $code): void
    {
        $this->code = $code;
    }

    // Защита от случая, когда фронт по ошибке пересылает сюда весь
    // фрагмент URL (всё, что после '#'), склеенный со state — отбрасываем
    // хвост после первого '#', оставляя только сам state.
    public function getState(): string
    {
        return explode('#', $this->state, 2)[0];
    }
}
