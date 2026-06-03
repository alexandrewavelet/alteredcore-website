<?php

namespace AlteredCore\EquinoxDeckImport\Http;

/**
 * Serializes a JSON response for the plugin's API endpoints.
 */
final class Json
{
    /**
     * @param array<string,mixed> $payload
     */
    public static function send(array $payload, int $status = 200): void
    {
        if (!headers_sent()) {
            http_response_code($status);
            header('Content-Type: application/json; charset=UTF-8');
        }
        echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    }
}
