<?php

namespace AlteredCore\EquinoxDeckImport\Port;

use RuntimeException;

/**
 * Thrown by a DeckApiClient when a request fails at the transport/HTTP level.
 * Carries a debug payload (url, http, curl_error, response_preview) the caller
 * can surface when diagnostics are requested.
 */
final class DeckApiException extends RuntimeException
{
    /** @var array<string,mixed> */
    private array $debug;

    /**
     * @param array<string,mixed> $debug
     */
    public function __construct(string $message, array $debug = [])
    {
        parent::__construct($message);
        $this->debug = $debug;
    }

    /**
     * @return array<string,mixed>
     */
    public function debug(): array
    {
        return $this->debug;
    }
}
