<?php

namespace App\Dto\OAuth;

use Symfony\Component\Serializer\Attribute\Groups;

/**
 * Output-DTO для GET /auth/{provider}/url (AbstractOAuthUrlController) —
 * ApiResource биндит его как output: GeneralAuthUrlOutput::class на
 * стороне операции. Единственное поле — полностью собранный URL согласия
 * провайдера (уже с anti-CSRF state внутри query), на который фронтенд
 * должен сделать redirect.
 */
final class GeneralAuthUrlOutput
{
    #[Groups([
        'google:read',
        'instagram:read',
        'facebook:read',
    ])]
    public string $url;
}
