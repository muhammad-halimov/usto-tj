<?php

namespace App\Dto\TechSupport;

class TechSupportPostInput extends TechSupportInput
{
    // Обязателен только для неавторизованных пользователей (гостей).
    // Авторизованным пользователям email не нужен — контакт идёт через аккаунт.
    public ?string $guestEmail = null;
}
