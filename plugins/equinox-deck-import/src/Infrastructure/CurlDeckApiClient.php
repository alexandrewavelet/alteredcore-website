<?php

namespace AlteredCore\EquinoxDeckImport\Infrastructure;

use AlteredCore\EquinoxDeckImport\Domain\Card;
use AlteredCore\EquinoxDeckImport\Domain\Deck;
use AlteredCore\EquinoxDeckImport\Port\DeckApiClientInterface;
use AlteredCore\EquinoxDeckImport\Port\DeckApiException;
use AlteredCore\EquinoxDeckImport\Port\TokenProviderInterface;

/**
 * Talks to the Altered Deck API over cURL using the current user's bearer token.
 */
final class CurlDeckApiClient implements DeckApiClientInterface
{
    private TokenProviderInterface $tokens;
    private string $baseUrl;

    public function __construct(TokenProviderInterface $tokens, ?string $baseUrl = null)
    {
        $this->tokens = $tokens;
        $this->baseUrl = $baseUrl ?? (defined('DECKS_API_URL') ? \DECKS_API_URL : '');
    }

    public function fetchUserDecks(): array
    {
        $path = '/api/decks';
        $response = $this->request('GET', $path);
        if ($response['body'] === false || $response['curl_error'] !== '' || $response['http'] !== 200) {
            throw new DeckApiException('Failed to fetch user decks', $this->debugFrom($path, $response));
        }

        $data = json_decode($response['body'], true);
        if (!is_array($data)) {
            throw new DeckApiException('Malformed user-decks response', $this->debugFrom($path, $response));
        }

        return $this->unwrapList($data);
    }

    public function fetchDecksByIds(array $ids, array &$errors = []): array
    {
        $errors = [];
        if ($ids === []) {
            return [];
        }

        $multi = curl_multi_init();
        $handles = $this->openHandles($multi, $ids);
        $this->runMulti($multi);
        $results = $this->collectHandles($multi, $handles, $errors);
        curl_multi_close($multi);

        return $results;
    }

    public function createDeck(Deck $deck): array
    {
        $cards = array_map(static function (Card $card): array {
            return $card->toApiArray();
        }, $deck->normalizedCards());

        if ($cards === []) {
            return $this->createOutcome(false, null, 0, 'no valid cards', '');
        }

        $response = $this->request('POST', '/api/decks', $this->payloadFor($deck, $cards));

        return $this->interpretCreate($response);
    }

    /**
     * Perform a single request and return its raw outcome.
     *
     * @return array{body: string|false, http: int, curl_error: string}
     */
    private function request(string $method, string $path, ?string $payload = null): array
    {
        $ch = curl_init($this->baseUrl . $path);
        $options = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_HTTPHEADER => $this->headers($payload !== null),
            CURLOPT_TIMEOUT => 20,
        ] + $this->sslOptions();
        if ($payload !== null) {
            $options[CURLOPT_POSTFIELDS] = $payload;
        }
        curl_setopt_array($ch, $options);

        $body = curl_exec($ch);
        $outcome = [
            'body' => $body,
            'http' => (int) curl_getinfo($ch, CURLINFO_HTTP_CODE),
            'curl_error' => curl_error($ch),
        ];
        curl_close($ch);

        return $outcome;
    }

    /**
     * @param string[] $ids
     * @return array<string, resource>
     */
    private function openHandles($multi, array $ids): array
    {
        $headers = $this->headers(false);
        $ssl = $this->sslOptions();
        $handles = [];
        foreach ($ids as $id) {
            $ch = curl_init($this->baseUrl . '/api/decks/' . rawurlencode((string) $id));
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HTTPHEADER => $headers,
                CURLOPT_TIMEOUT => 20,
            ] + $ssl);
            curl_multi_add_handle($multi, $ch);
            $handles[(string) $id] = $ch;
        }

        return $handles;
    }

    private function runMulti($multi): void
    {
        $running = null;
        do {
            curl_multi_exec($multi, $running);
            if ($running > 0) {
                // Block until activity; usleep avoids a 100% CPU busy-spin when
                // curl_multi_select returns -1 (no descriptors / select error).
                if (curl_multi_select($multi) === -1) {
                    usleep(1000);
                }
            }
        } while ($running > 0);
    }

    /**
     * @param array<string, resource> $handles
     * @param array<string,mixed>     $errors
     * @return array<string, array>
     */
    private function collectHandles($multi, array $handles, array &$errors): array
    {
        $results = [];
        foreach ($handles as $id => $ch) {
            $body = curl_multi_getcontent($ch);
            $http = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlError = curl_error($ch);
            $effectiveUrl = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
            curl_multi_remove_handle($multi, $ch);

            $data = ($body !== false && $http === 200) ? json_decode($body, true) : null;
            if (is_array($data)) {
                $results[$id] = $data;

                continue;
            }
            $errors[$id] = [
                'http' => $http,
                'curl_error' => $curlError,
                'url' => $effectiveUrl,
                'preview' => mb_substr((string) $body, 0, 200),
            ];
        }

        return $results;
    }

    /**
     * @param array{body: string|false, http: int, curl_error: string} $response
     * @return array{ok: bool, id: ?string, http: int, error: string, response_preview: string}
     */
    private function interpretCreate(array $response): array
    {
        if ($response['body'] === false || $response['curl_error'] !== '') {
            $error = $response['curl_error'] !== '' ? $response['curl_error'] : 'curl error';

            return $this->createOutcome(false, null, 0, $error, '');
        }

        $preview = mb_substr((string) $response['body'], 0, 800);
        $http = $response['http'];
        if ($http >= 200 && $http < 300) {
            $data = json_decode($response['body'], true);

            return $this->createOutcome(true, $data['id'] ?? null, $http, '', $preview);
        }

        $body = json_decode($response['body'], true);
        $message = $body['message'] ?? $body['error'] ?? ('HTTP ' . $http);

        return $this->createOutcome(false, null, $http, $message, $preview);
    }

    /**
     * @return array{ok: bool, id: ?string, http: int, error: string, response_preview: string}
     */
    private function createOutcome(bool $ok, ?string $id, int $http, string $error, string $preview): array
    {
        return ['ok' => $ok, 'id' => $id, 'http' => $http, 'error' => $error, 'response_preview' => $preview];
    }

    /**
     * @param array<int,array> $cards
     */
    private function payloadFor(Deck $deck, array $cards): string
    {
        return json_encode([
            'name' => $deck->name() !== '' ? $deck->name() : 'Imported deck',
            'format' => $deck->format(),
            'isPublic' => false,
            'isDraft' => false,
            'deckCards' => $cards,
        ], JSON_UNESCAPED_UNICODE);
    }

    /**
     * Unwrap a deck list from a flat array or a {items|decks|data: [...]} wrapper.
     *
     * @param array<string,mixed> $data
     * @return array<int,array>
     */
    private function unwrapList(array $data): array
    {
        foreach (['items', 'decks', 'data'] as $key) {
            if (isset($data[$key]) && is_array($data[$key])) {
                return $data[$key];
            }
        }

        return $data;
    }

    /**
     * @param array{body: string|false, http: int, curl_error: string} $response
     * @return array<string,mixed>
     */
    private function debugFrom(string $path, array $response): array
    {
        return [
            'url' => $this->baseUrl . $path,
            'http' => $response['http'],
            'curl_error' => $response['curl_error'],
            'response_preview' => $response['body'] !== false ? (string) $response['body'] : '',
        ];
    }

    /**
     * @return string[]
     */
    private function headers(bool $withJson): array
    {
        $headers = [
            'Accept: application/json',
            'Authorization: Bearer ' . $this->tokens->accessToken(),
        ];
        if ($withJson) {
            $headers[] = 'Content-Type: application/json';
        }

        return $headers;
    }

    /**
     * TLS verification is relaxed only when DEV_MODE is defined and truthy.
     *
     * @return array<int,mixed>
     */
    private function sslOptions(): array
    {
        $devMode = defined('DEV_MODE') && \DEV_MODE;

        return [
            CURLOPT_SSL_VERIFYPEER => !$devMode,
            CURLOPT_SSL_VERIFYHOST => $devMode ? 0 : 2,
        ];
    }
}
