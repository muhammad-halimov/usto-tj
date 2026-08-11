<?php

namespace App\Dto\TechSupport;

use App\Entity\Appeal\Reason\AppealReason;

/**
 * Общая база для POST (TechSupportPostInput) и PATCH (TechSupportPatchInput) —
 * поля, которые пишутся в обоих случаях. У каждого наследника — свои
 * дополнительные поля: у POST это guestEmail, у PATCH — status и images.
 * Тот же паттерн, что TicketInput → TicketPatchInput.
 */
class TechSupportInput
{
    public ?string       $title       = null;
    public ?AppealReason $reason      = null;
    public ?string       $priority    = null;
    public ?string       $description = null;
}
