<?php

namespace AlteredCore\EquinoxDeckImport\Infrastructure;

use AlteredCore\EquinoxDeckImport\Port\TokenProviderInterface;

/**
 * Obtains the current user's Deck API bearer token from the host's core
 * Keycloak helper. Depends only on host functions (`kcIsLoggedIn`,
 * `kc_get_access_token`) that `includes/functions.php` always loads — no
 * dependency on any other plugin. Returns '' when unavailable.
 */
final class KeycloakTokenProvider implements TokenProviderInterface
{
    public function accessToken(): string
    {
        if (!function_exists('kcIsLoggedIn') || !\kcIsLoggedIn()) {
            return '';
        }
        if (!function_exists('kc_get_access_token')) {
            return '';
        }
        $userId = (int) ($_SESSION['user_id'] ?? 0);
        if ($userId <= 0) {
            return '';
        }
        $token = \kc_get_access_token($userId);
        return (is_string($token) && $token !== '') ? $token : '';
    }
}
