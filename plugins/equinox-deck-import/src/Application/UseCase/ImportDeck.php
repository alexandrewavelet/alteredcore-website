<?php

namespace AlteredCore\EquinoxDeckImport\Application\UseCase;

use AlteredCore\EquinoxDeckImport\Application\Dto\ImportResult;
use AlteredCore\EquinoxDeckImport\Domain\Card;
use AlteredCore\EquinoxDeckImport\Domain\Deck;
use AlteredCore\EquinoxDeckImport\Port\DeckApiClientInterface;
use AlteredCore\EquinoxDeckImport\Port\TokenProviderInterface;
use DomainException;

/**
 * Use case behind the `import-deck` endpoint: validate one deck, skip it if an
 * identical deck already exists (content hash over the candidate ids the parse
 * step found), otherwise create it via the Deck API.
 */
final class ImportDeck
{
    private DeckApiClientInterface $api;
    private TokenProviderInterface $tokens;

    public function __construct(DeckApiClientInterface $api, TokenProviderInterface $tokens)
    {
        $this->api    = $api;
        $this->tokens = $tokens;
    }

    /**
     * @param array<string,mixed>  $body  decoded JSON request body
     * @param array<string,string> $msg   localized import messages
     */
    public function execute(array $body, array $msg, bool $debugRequested): ImportResult
    {
        $name        = trim((string) ($body['name'] ?? ''));
        $format      = trim((string) ($body['format'] ?? 'standard'));
        $hero        = trim((string) ($body['hero'] ?? ''));
        $cardsRaw    = $body['cards'] ?? [];
        $matchingIds = $body['matching_ids'] ?? [];

        if ($name === '' || !is_array($cardsRaw) || empty($cardsRaw)) {
            return ImportResult::rejected(400, 'invalid_deck', $msg['invalid_deck']);
        }

        $cards = [];
        foreach ($cardsRaw as $c) {
            if (!is_array($c)) {
                return ImportResult::rejected(400, 'invalid_card', $msg['invalid_card']);
            }
            try {
                $cards[] = new Card((string) ($c['cardReference'] ?? ''), (int) ($c['quantity'] ?? 0));
            } catch (DomainException $e) {
                return ImportResult::rejected(400, 'invalid_card', $msg['invalid_card']);
            }
        }
        $deck = new Deck($name, $format, $hero, $cards);

        if ($this->tokens->accessToken() === '') {
            return ImportResult::rejected(401, 'no_token', $msg['no_token']);
        }

        $incomingHash = $deck->contentHash();

        $debug = $debugRequested ? [
            'request' => [
                'name'             => $name,
                'format'           => $format,
                'hero'             => $hero,
                'card_count'       => count($cards),
                'normalized_count' => count($deck->normalizedCards()),
                'incoming_hash'    => $incomingHash,
            ],
            'dedup' => [
                'matching_ids' => array_values(array_map('strval', (array) $matchingIds)),
                'checked'      => [],
                'fetch_errors' => [],
                'decision'     => 'import',
            ],
            'import' => null,
        ] : [];

        // Dedup: fetch full details only for the candidate ids (≈ ≤2 API calls).
        if (!empty($matchingIds) && is_array($matchingIds)) {
            $safeIds = array_values(array_filter(array_map('strval', $matchingIds)));
            $errors  = [];
            $full    = $this->api->fetchDecksByIds($safeIds, $errors);
            if ($debugRequested) {
                $debug['dedup']['fetch_errors'] = array_slice($errors, 0, 3, true);
            }
            foreach ($full as $fid => $apiDeck) {
                $apiCards = $apiDeck['deckCards'] ?? $apiDeck['cards'] ?? [];
                $apiHash  = Deck::hashFrom((string) ($apiDeck['name'] ?? $name), is_array($apiCards) ? $apiCards : []);
                $isMatch  = ($apiHash === $incomingHash);
                if ($debugRequested) {
                    $debug['dedup']['checked'][] = [
                        'id'          => (string) $fid,
                        'name'        => (string) ($apiDeck['name'] ?? ''),
                        'cards_count' => is_array($apiCards) ? count($apiCards) : 0,
                        'api_hash'    => $apiHash,
                        'matched'     => $isMatch,
                    ];
                }
                if ($isMatch) {
                    if ($debugRequested) {
                        $debug['dedup']['decision'] = 'skip';
                    }
                    return ImportResult::skipped($debug);
                }
            }
        }

        $result = $this->api->createDeck($deck);
        if ($debugRequested) {
            $debug['import'] = [
                'http'             => (int) ($result['http'] ?? 0),
                'response_preview' => (string) ($result['response_preview'] ?? ''),
                'error'            => (string) ($result['error'] ?? ''),
            ];
        }

        if (!empty($result['ok'])) {
            return ImportResult::imported($result['id'] ?? null, $debug);
        }

        $http   = (int) ($result['http'] ?? 0);
        $apiErr = (string) ($result['error'] ?? '');
        if ($apiErr !== '') {
            error_log(sprintf('[equinox-deck-import] import failed HTTP %d for deck "%s": %s', $http, $name, $apiErr));
        }
        $key = $this->errorKey($http, $http === 0 ? $apiErr : '');
        return ImportResult::apiError('http_' . $http, $msg[$key] ?? $msg['err_generic'], $debug);
    }

    /**
     * Map an HTTP code / transport error to a localized message key.
     */
    private function errorKey(int $http, string $curlErr): string
    {
        if ($curlErr !== '' || $http === 0) {
            return 'err_network';
        }
        if ($http === 401) {
            return 'err_session';
        }
        if ($http === 429) {
            return 'err_rate';
        }
        if ($http === 400) {
            return 'err_bad_request';
        }
        if (in_array($http, [502, 503, 504], true)) {
            return 'err_unavailable';
        }
        if ($http >= 500) {
            return 'err_server';
        }
        return 'err_generic';
    }
}
