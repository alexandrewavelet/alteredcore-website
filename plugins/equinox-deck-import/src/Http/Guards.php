<?php

namespace AlteredCore\EquinoxDeckImport\Http;

/**
 * Thin wrappers over the host's auth / CSRF helpers, safe to call even if those
 * functions are somehow unavailable. Endpoints decide the JSON shape to return
 * on failure (it differs between parse-zip and import-deck).
 */
final class Guards
{
    public static function isLoggedIn(): bool
    {
        return function_exists('kcIsLoggedIn') && \kcIsLoggedIn();
    }

    public static function csrfValid(?string $token): bool
    {
        return function_exists('csrfValid') && \csrfValid($token);
    }
}
