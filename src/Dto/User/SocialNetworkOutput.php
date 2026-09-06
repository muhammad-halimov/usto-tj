<?php

namespace App\Dto\User;

class SocialNetworkOutput
{
    // string, а не int (06.09.2026, переход на UUID-PK) — SocialNetwork::$id теперь UUID.
    public string $id;
    public string $network;
}
