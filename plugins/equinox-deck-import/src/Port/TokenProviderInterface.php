<?php

namespace AlteredCore\EquinoxDeckImport\Port;

/**
 * Supplies the bearer token used to authenticate Deck API calls for the current
 * user. Returns an empty string when no token is available (not logged in /
 * session expired).
 */
interface TokenProviderInterface
{
    public function accessToken(): string;
}
