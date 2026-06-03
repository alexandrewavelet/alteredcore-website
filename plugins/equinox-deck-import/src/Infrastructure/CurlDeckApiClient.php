<?php

namespace AlteredCore\EquinoxDeckImport\Infrastructure;

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
        $this->tokens  = $tokens;
        $this->baseUrl = $baseUrl ?? (defined('DECKS_API_URL') ? \DECKS_API_URL : '');
    }

    public function fetchUserDecks(): array
    {
        $url   = $this->baseUrl . '/api/decks';
        $debug = ['url' => $url, 'http' => 0, 'curl_error' => '', 'response_preview' => ''];

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => $this->authHeaders(),
            CURLOPT_TIMEOUT        => 20,
        ] + $this->sslOptions());

        $resp     = curl_exec($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErr  = curl_error($ch);
        curl_close($ch);

        $debug['http']             = $httpCode;
        $debug['curl_error']       = $curlErr;
        $debug['response_preview'] = $resp !== false ? (string) $resp : '';

        if ($resp === false || $curlErr !== '' || $httpCode !== 200) {
            throw new DeckApiException('Failed to fetch user decks', $debug);
        }

        $data = json_decode($resp, true);
        if (!is_array($data)) {
            throw new DeckApiException('Malformed user-decks response', $debug);
        }

        // Handle both flat arrays and wrapped responses: {items|decks|data: [...]}
        foreach (['items', 'decks', 'data'] as $key) {
            if (isset($data[$key]) && is_array($data[$key])) {
                return $data[$key];
            }
        }
        return $data;
    }

    public function fetchDecksByIds(array $ids, array &$errors = []): array
    {
        $errors = [];
        if (empty($ids)) {
            return [];
        }

        $headers = $this->authHeaders();
        $ssl     = $this->sslOptions();
        $mh      = curl_multi_init();
        $handles = [];

        foreach ($ids as $id) {
            $ch = curl_init($this->baseUrl . '/api/decks/' . rawurlencode((string) $id));
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HTTPHEADER     => $headers,
                CURLOPT_TIMEOUT        => 20,
            ] + $ssl);
            curl_multi_add_handle($mh, $ch);
            $handles[(string) $id] = $ch;
        }

        $running = null;
        do {
            curl_multi_exec($mh, $running);
            if ($running > 0) {
                // Block until activity; usleep avoids a 100% CPU busy-spin when
                // curl_multi_select returns -1 (no descriptors / select error).
                if (curl_multi_select($mh) === -1) {
                    usleep(1000);
                }
            }
        } while ($running > 0);

        $results = [];
        foreach ($handles as $id => $ch) {
            $resp         = curl_multi_getcontent($ch);
            $httpCode     = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlErr      = curl_error($ch);
            $effectiveUrl = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
            curl_multi_remove_handle($mh, $ch);

            if ($resp !== false && $httpCode === 200) {
                $data = json_decode($resp, true);
                if (is_array($data)) {
                    $results[$id] = $data;
                    continue;
                }
            }
            $errors[$id] = [
                'http'       => $httpCode,
                'curl_error' => $curlErr,
                'url'        => $effectiveUrl,
                'preview'    => mb_substr((string) $resp, 0, 200),
            ];
        }

        curl_multi_close($mh);
        return $results;
    }

    public function createDeck(Deck $deck): array
    {
        $cards = [];
        foreach ($deck->normalizedCards() as $card) {
            $cards[] = $card->toApiArray();
        }
        if (empty($cards)) {
            return ['ok' => false, 'id' => null, 'http' => 0, 'error' => 'no valid cards', 'response_preview' => ''];
        }

        $payload = json_encode([
            'name'      => $deck->name() !== '' ? $deck->name() : 'Imported deck',
            'format'    => $deck->format(),
            'isPublic'  => false,
            'isDraft'   => false,
            'deckCards' => $cards,
        ], JSON_UNESCAPED_UNICODE);

        $ch = curl_init($this->baseUrl . '/api/decks');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST  => 'POST',
            CURLOPT_HTTPHEADER     => array_merge($this->authHeaders(), ['Content-Type: application/json']),
            CURLOPT_POSTFIELDS     => $payload,
            CURLOPT_TIMEOUT        => 20,
        ] + $this->sslOptions());

        $resp     = curl_exec($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErr  = curl_error($ch);
        curl_close($ch);

        if ($resp === false || $curlErr !== '') {
            return ['ok' => false, 'id' => null, 'http' => 0, 'error' => $curlErr !== '' ? $curlErr : 'curl error', 'response_preview' => ''];
        }

        $preview = mb_substr((string) $resp, 0, 800);
        if ($httpCode >= 200 && $httpCode < 300) {
            $data = json_decode($resp, true);
            return ['ok' => true, 'id' => $data['id'] ?? null, 'http' => $httpCode, 'error' => '', 'response_preview' => $preview];
        }

        $body = json_decode($resp, true);
        $msg  = $body['message'] ?? $body['error'] ?? ('HTTP ' . $httpCode);
        return ['ok' => false, 'id' => null, 'http' => $httpCode, 'error' => $msg, 'response_preview' => $preview];
    }

    /**
     * @return string[]
     */
    private function authHeaders(): array
    {
        return [
            'Accept: application/json',
            'Authorization: Bearer ' . $this->tokens->accessToken(),
        ];
    }

    /**
     * TLS verification is only relaxed when DEV_MODE is defined AND truthy.
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
