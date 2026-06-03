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
        $this->csv    = $csv;
        $this->parser = $parser;
        $this->api    = $api;
        $this->tokens = $tokens;
    }

    /**
     * @param array|null          $file  the $_FILES['equinox_zip'] entry
     * @param array<string,string> $msg  localized parse messages
     */
    public function execute(?array $file, array $msg, bool $debugRequested): ParseResult
    {
        if (!$this->csv->isSupported()) {
            return ParseResult::failure(500, $msg['no_zipext']);
        }
        if ($file === null || !is_array($file)
            || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK
            || (int) ($file['size'] ?? 0) === 0) {
            return ParseResult::failure(400, $msg['no_file']);
        }
        if (strtolower(pathinfo((string) ($file['name'] ?? ''), PATHINFO_EXTENSION)) !== 'zip') {
            return ParseResult::failure(400, $msg['not_zip']);
        }

        $raw = $this->csv->read((string) $file['tmp_name']);
        if ($raw === null) {
            return ParseResult::failure(400, $msg['cant_read']);
        }
        if ($raw === '') {
            return ParseResult::failure(400, $msg['empty_csv']);
        }

        $decks = $this->parser->parse($raw);
        if (empty($decks)) {
            return ParseResult::failure(400, $msg['no_decks']);
        }

        $token        = $this->tokens->accessToken();
        $tokenPresent = $token !== '';

        $incomingNames = array_map(function (Deck $d): string {
            return $d->name();
        }, $decks);

        $debug = $debugRequested ? [
            'token_present'     => $tokenPresent,
            'dedup_warn'        => false,
            'dedup_warn_reason' => '',
            'fetch'             => null,
            'parsed_deck_count' => count($decks),
            'api_deck_count'    => 0,
            'incoming_names'    => array_map(function (string $n): array {
                return ['value' => $n, 'hex' => bin2hex($n)];
            }, $incomingNames),
            'api_names'         => [],
            'near_misses'       => [],
        ] : [];

        $dedupWarn = false;
        $nameToIds = []; // lowercase name => [id, ...]

        if (!$tokenPresent) {
            $dedupWarn = true;
            if ($debugRequested) {
                $debug['dedup_warn_reason'] = 'no_token';
            }
        } else {
            try {
                $existing = $this->api->fetchUserDecks();
                if ($debugRequested) {
                    $debug['api_deck_count'] = count($existing);
                    $debug['api_names'] = array_map(function ($d): array {
                        $nm = (string) ($d['name'] ?? '');
                        return ['value' => $nm, 'hex' => bin2hex($nm)];
                    }, $existing);
                    $debug['near_misses'] = $this->nearMisses($incomingNames, $existing);
                }
                foreach ($existing as $d) {
                    $name = mb_strtolower(trim((string) ($d['name'] ?? '')));
                    $id   = $d['id'] ?? null;
                    if ($name !== '' && $id !== null) {
                        $nameToIds[$name][] = (string) $id;
                    }
                }
            } catch (DeckApiException $e) {
                $dedupWarn = true;
                if ($debugRequested) {
                    $debug['dedup_warn_reason'] = 'fetch_failed';
                    $fd = $e->debug();
                    if (isset($fd['response_preview'])) {
                        $fd['response_preview'] = mb_substr((string) $fd['response_preview'], 0, 1000);
                    }
                    $debug['fetch'] = $fd;
                }
            }
        }
        if ($debugRequested) {
            $debug['dedup_warn'] = $dedupWarn;
        }

        $out = [];
        foreach ($decks as $deck) {
            $row                 = $deck->toArray();
            $nameLower           = mb_strtolower(trim($deck->name()));
            $row['matching_ids'] = $dedupWarn ? [] : ($nameToIds[$nameLower] ?? []);
            $out[]               = $row;
        }

        return ParseResult::success($out, $dedupWarn, $tokenPresent, $debug);
    }

    /**
     * Names that match case-insensitively but differ byte-for-byte (diagnostic).
     *
     * @param string[]          $incomingNames
     * @param array<int,array>  $existing
     * @return array<int,array>
     */
    private function nearMisses(array $incomingNames, array $existing): array
    {
        $out = [];
        foreach ($incomingNames as $iname) {
            foreach ($existing as $d) {
                $aname = (string) ($d['name'] ?? '');
                if ($iname !== $aname && mb_strtolower(trim($iname)) === mb_strtolower(trim($aname))) {
                    $out[] = [
                        'incoming'     => $iname,
                        'api'          => $aname,
                        'incoming_hex' => bin2hex($iname),
                        'api_hex'      => bin2hex($aname),
                    ];
                }
            }
        }
        return $out;
    }
}
