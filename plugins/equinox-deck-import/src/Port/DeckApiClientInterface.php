<?php

namespace AlteredCore\EquinoxDeckImport\Port;

use AlteredCore\EquinoxDeckImport\Domain\Deck;

/**
 * Port for talking to the Altered Deck API. Implementations live in
 * Infrastructure; the application use cases depend only on this interface.
 */
interface DeckApiClientInterface
{
    /**
     * The authenticated user's existing decks (raw API summary arrays).
     *
     * @return array<int, array>
     * @throws DeckApiException on transport/HTTP failure
     */
    public function fetchUserDecks(): array;

    /**
     * Full details (including deckCards) for the given deck ids, fetched in
     * parallel. Missing entries = that id failed to fetch.
     *
     * @param string[]            $ids
     * @param array<string,mixed> $errors out: id => {http, curl_error, url, preview}
     * @return array<string, array>       id => full deck array
     */
    public function fetchDecksByIds(array $ids, array &$errors = []): array;

    /**
     * Create one deck.
     *
     * @return array{ok: bool, id: ?string, http: int, error: string, response_preview: string}
     */
    public function createDeck(Deck $deck): array;
}
