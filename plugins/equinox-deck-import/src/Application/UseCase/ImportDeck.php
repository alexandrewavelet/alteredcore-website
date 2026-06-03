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
        $this->api = $api;
        $this->tokens = $tokens;
    }

    /**
     * @param array<string,mixed>  $body     decoded JSON request body
     * @param array<string,string> $messages localized import messages
     */
    public function execute(array $body, array $messages, bool $withDebug): ImportResult
    {
        $name = trim((string) ($body['name'] ?? ''));
        $cardsRaw = $body['cards'] ?? [];
        if ($name === '' || !is_array($cardsRaw) || $cardsRaw === []) {
            return ImportResult::rejected(400, 'invalid_deck', $messages['invalid_deck']);
        }

        $cards = $this->toCards($cardsRaw);
        if ($cards === null) {
            return ImportResult::rejected(400, 'invalid_card', $messages['invalid_card']);
        }

        if ($this->tokens->accessToken() === '') {
            return ImportResult::rejected(401, 'no_token', $messages['no_token']);
        }

        $deck = new Deck($name, trim((string) ($body['format'] ?? 'standard')), trim((string) ($body['hero'] ?? '')), $cards);
        $dedup = $this->checkDuplicate($deck, $this->matchingIds($body));

        if ($dedup['matched']) {
            return ImportResult::skipped($withDebug ? $this->debug($deck, $dedup, null) : []);
        }

        return $this->createDeck($deck, $dedup, $messages, $withDebug);
    }

    /**
     * Build the card list, returning null if any card is invalid.
     *
     * @param array<int,mixed> $cardsRaw
     * @return Card[]|null
     */
    private function toCards(array $cardsRaw): ?array
    {
        $cards = [];
        foreach ($cardsRaw as $raw) {
            if (!is_array($raw)) {
                return null;
            }
            try {
                $cards[] = new Card((string) ($raw['cardReference'] ?? ''), (int) ($raw['quantity'] ?? 0));
            } catch (DomainException $e) {
                return null;
            }
        }

        return $cards;
    }

    /**
     * @param array<string,mixed> $body
     * @return string[]
     */
    private function matchingIds(array $body): array
    {
        $ids = $body['matching_ids'] ?? [];
        if (!is_array($ids)) {
            return [];
        }

        return array_values(array_filter(array_map('strval', $ids)));
    }

    /**
     * Compare the deck against its candidate duplicates by content hash.
     *
     * @param string[] $matchingIds
     * @return array{matched: bool, incoming_hash: string, matching_ids: string[], checked: array, fetch_errors: array}
     */
    private function checkDuplicate(Deck $deck, array $matchingIds): array
    {
        $dedup = [
            'matched' => false,
            'incoming_hash' => $deck->contentHash(),
            'matching_ids' => $matchingIds,
            'checked' => [],
            'fetch_errors' => [],
        ];
        if ($matchingIds === []) {
            return $dedup;
        }

        $errors = [];
        $candidates = $this->api->fetchDecksByIds($matchingIds, $errors);
        $dedup['fetch_errors'] = array_slice($errors, 0, 3, true);

        foreach ($candidates as $id => $apiDeck) {
            $apiCards = $apiDeck['deckCards'] ?? $apiDeck['cards'] ?? [];
            $apiCards = is_array($apiCards) ? $apiCards : [];
            $apiHash = Deck::hashFrom((string) ($apiDeck['name'] ?? $deck->name()), $apiCards);
            $matched = $apiHash === $dedup['incoming_hash'];
            $dedup['checked'][] = [
                'id' => (string) $id,
                'name' => (string) ($apiDeck['name'] ?? ''),
                'cards_count' => count($apiCards),
                'api_hash' => $apiHash,
                'matched' => $matched,
            ];
            if ($matched) {
                $dedup['matched'] = true;

                break;
            }
        }

        return $dedup;
    }

    /**
     * @param array<string,string> $messages
     */
    private function createDeck(Deck $deck, array $dedup, array $messages, bool $withDebug): ImportResult
    {
        $api = $this->api->createDeck($deck);
        $debug = $withDebug ? $this->debug($deck, $dedup, $api) : [];

        if (!empty($api['ok'])) {
            return ImportResult::imported($api['id'] ?? null, $debug);
        }

        $http = (int) ($api['http'] ?? 0);
        $this->logFailure($http, $deck->name(), (string) ($api['error'] ?? ''));

        return ImportResult::apiError('http_' . $http, $messages[$this->messageKey($http)] ?? $messages['err_generic'], $debug);
    }

    private function logFailure(int $http, string $deckName, string $apiError): void
    {
        if ($apiError !== '') {
            error_log(sprintf('[equinox-deck-import] import failed HTTP %d for deck "%s": %s', $http, $deckName, $apiError));
        }
    }

    /**
     * Map an HTTP status to a localized message key (http 0 = transport failure).
     */
    private function messageKey(int $http): string
    {
        if ($http === 0) {
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

    /**
     * @param array<string,mixed>|null $api
     * @return array<string,mixed>
     */
    private function debug(Deck $deck, array $dedup, ?array $api): array
    {
        $debug = [
            'request' => [
                'name' => $deck->name(),
                'format' => $deck->format(),
                'hero' => $deck->hero(),
                'card_count' => count($deck->cards()),
                'normalized_count' => count($deck->normalizedCards()),
                'incoming_hash' => $dedup['incoming_hash'],
            ],
            'dedup' => [
                'matching_ids' => $dedup['matching_ids'],
                'checked' => $dedup['checked'],
                'fetch_errors' => $dedup['fetch_errors'],
                'decision' => $dedup['matched'] ? 'skip' : 'import',
            ],
            'import' => null,
        ];
        if ($api !== null) {
            $debug['import'] = [
                'http' => (int) ($api['http'] ?? 0),
                'response_preview' => (string) ($api['response_preview'] ?? ''),
                'error' => (string) ($api['error'] ?? ''),
            ];
        }

        return $debug;
    }
}
