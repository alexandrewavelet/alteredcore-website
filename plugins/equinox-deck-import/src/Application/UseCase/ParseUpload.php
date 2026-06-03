<?php

namespace AlteredCore\EquinoxDeckImport\Application\UseCase;

use AlteredCore\EquinoxDeckImport\Application\Dto\ParseResult;
use AlteredCore\EquinoxDeckImport\Domain\Deck;
use AlteredCore\EquinoxDeckImport\Domain\DeckParser;
use AlteredCore\EquinoxDeckImport\Port\DeckApiClientInterface;
use AlteredCore\EquinoxDeckImport\Port\DeckApiException;
use AlteredCore\EquinoxDeckImport\Port\DeckCsvReaderInterface;
use AlteredCore\EquinoxDeckImport\Port\TokenProviderInterface;

/**
 * Use case behind the `parse-zip` endpoint: read the uploaded ZIP, parse the
 * decks, and prepare duplicate-detection data (each deck gets the ids of the
 * user's existing decks that share its name, for the per-deck import step).
 */
final class ParseUpload
{
    private DeckCsvReaderInterface $csv;
    private DeckParser $parser;
    private DeckApiClientInterface $api;
    private TokenProviderInterface $tokens;

    public function __construct(
        DeckCsvReaderInterface $csv,
        DeckParser $parser,
        DeckApiClientInterface $api,
        TokenProviderInterface $tokens
    ) {
        $this->csv = $csv;
        $this->parser = $parser;
        $this->api = $api;
        $this->tokens = $tokens;
    }

    /**
     * @param array|null           $file     the $_FILES['equinox_zip'] entry
     * @param array<string,string> $messages localized parse messages
     */
    public function execute(?array $file, array $messages, bool $withDebug): ParseResult
    {
        $rejection = $this->rejectInvalidUpload($file, $messages);
        if ($rejection !== null) {
            return $rejection;
        }

        $csv = $this->csv->read((string) $file['tmp_name']);
        $rejection = $this->rejectUnreadableCsv($csv, $messages);
        if ($rejection !== null) {
            return $rejection;
        }

        $decks = $this->parser->parse((string) $csv);
        if ($decks === []) {
            return ParseResult::failure(400, $messages['no_decks']);
        }

        $tokenPresent = $this->tokens->accessToken() !== '';
        $dedup = $this->resolveDuplicateIndex($tokenPresent);

        return ParseResult::success(
            $this->withMatchingIds($decks, $dedup),
            $dedup['warn'],
            $tokenPresent,
            $withDebug ? $this->buildDebug($decks, $tokenPresent, $dedup) : []
        );
    }

    /**
     * @param array<string,string> $messages
     */
    private function rejectInvalidUpload(?array $file, array $messages): ?ParseResult
    {
        if (!$this->csv->isSupported()) {
            return ParseResult::failure(500, $messages['no_zipext']);
        }
        if ($file === null
            || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK
            || (int) ($file['size'] ?? 0) === 0) {
            return ParseResult::failure(400, $messages['no_file']);
        }
        if (strtolower(pathinfo((string) ($file['name'] ?? ''), PATHINFO_EXTENSION)) !== 'zip') {
            return ParseResult::failure(400, $messages['not_zip']);
        }

        return null;
    }

    /**
     * @param array<string,string> $messages
     */
    private function rejectUnreadableCsv(?string $csv, array $messages): ?ParseResult
    {
        if ($csv === null) {
            return ParseResult::failure(400, $messages['cant_read']);
        }
        if ($csv === '') {
            return ParseResult::failure(400, $messages['empty_csv']);
        }

        return null;
    }

    /**
     * Fetch the user's existing decks and index their ids by lowercased name.
     * On no token or a fetch failure, dedup is skipped (warn = true).
     *
     * @return array{warn: bool, reason: string, nameToIds: array, existing: array, fetch: ?array}
     */
    private function resolveDuplicateIndex(bool $tokenPresent): array
    {
        if (!$tokenPresent) {
            return ['warn' => true, 'reason' => 'no_token', 'nameToIds' => [], 'existing' => [], 'fetch' => null];
        }
        try {
            $existing = $this->api->fetchUserDecks();
        } catch (DeckApiException $e) {
            return ['warn' => true, 'reason' => 'fetch_failed', 'nameToIds' => [], 'existing' => [], 'fetch' => $e->debug()];
        }

        return ['warn' => false, 'reason' => '', 'nameToIds' => $this->indexByName($existing), 'existing' => $existing, 'fetch' => null];
    }

    /**
     * @param array<int,array> $existing
     * @return array<string, string[]>
     */
    private function indexByName(array $existing): array
    {
        $index = [];
        foreach ($existing as $deck) {
            $name = mb_strtolower(trim((string) ($deck['name'] ?? '')));
            $id = $deck['id'] ?? null;
            if ($name !== '' && $id !== null) {
                $index[$name][] = (string) $id;
            }
        }

        return $index;
    }

    /**
     * @param Deck[] $decks
     * @return array<int,array>
     */
    private function withMatchingIds(array $decks, array $dedup): array
    {
        return array_map(static function (Deck $deck) use ($dedup): array {
            $row = $deck->toArray();
            $key = mb_strtolower(trim($deck->name()));
            $row['matching_ids'] = $dedup['warn'] ? [] : ($dedup['nameToIds'][$key] ?? []);

            return $row;
        }, $decks);
    }

    /**
     * @param Deck[] $decks
     * @return array<string,mixed>
     */
    private function buildDebug(array $decks, bool $tokenPresent, array $dedup): array
    {
        $incomingNames = array_map(static function (Deck $d): string {
            return $d->name();
        }, $decks);

        $fetch = $dedup['fetch'];
        if (is_array($fetch) && isset($fetch['response_preview'])) {
            $fetch['response_preview'] = mb_substr((string) $fetch['response_preview'], 0, 1000);
        }

        return [
            'token_present' => $tokenPresent,
            'dedup_warn' => $dedup['warn'],
            'dedup_warn_reason' => $dedup['reason'],
            'fetch' => $fetch,
            'parsed_deck_count' => count($decks),
            'api_deck_count' => count($dedup['existing']),
            'incoming_names' => array_map([$this, 'withHex'], $incomingNames),
            'api_names' => array_map([$this, 'apiNameWithHex'], $dedup['existing']),
            'near_misses' => $this->nearMisses($incomingNames, $dedup['existing']),
        ];
    }

    /**
     * @return array{value: string, hex: string}
     */
    private function withHex(string $name): array
    {
        return ['value' => $name, 'hex' => bin2hex($name)];
    }

    /**
     * @param array<string,mixed> $deck
     * @return array{value: string, hex: string}
     */
    private function apiNameWithHex(array $deck): array
    {
        return $this->withHex((string) ($deck['name'] ?? ''));
    }

    /**
     * Names that match case-insensitively but differ byte-for-byte (diagnostic).
     *
     * @param string[]         $incomingNames
     * @param array<int,array> $existing
     * @return array<int,array>
     */
    private function nearMisses(array $incomingNames, array $existing): array
    {
        $out = [];
        foreach ($incomingNames as $incoming) {
            foreach ($existing as $deck) {
                $api = (string) ($deck['name'] ?? '');
                if ($incoming !== $api && mb_strtolower(trim($incoming)) === mb_strtolower(trim($api))) {
                    $out[] = [
                        'incoming' => $incoming,
                        'api' => $api,
                        'incoming_hex' => bin2hex($incoming),
                        'api_hex' => bin2hex($api),
                    ];
                }
            }
        }

        return $out;
    }
}
