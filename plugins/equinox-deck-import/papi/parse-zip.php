<?php
require_once dirname(__DIR__) . '/inc/functions.php';
header('Content-Type: application/json');

// Auth guard
if (!function_exists('kcIsLoggedIn') || !kcIsLoggedIn()) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'Non autorisé.']);
    exit;
}

// CSRF guard
if (!function_exists('csrfValid') || !csrfValid($_POST['csrf_token'] ?? '')) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Jeton de formulaire invalide.']);
    exit;
}

// ZipArchive guard
if (!class_exists('ZipArchive')) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => "L'extension ZipArchive n'est pas disponible sur ce serveur."]);
    exit;
}

// File guard
$f   = $_FILES['equinox_zip'] ?? null;
$ext = strtolower(pathinfo($f['name'] ?? '', PATHINFO_EXTENSION));

if (!$f || $f['error'] !== UPLOAD_ERR_OK || $f['size'] === 0) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Veuillez sélectionner un fichier .zip.']);
    exit;
}
if ($ext !== 'zip') {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Le fichier doit être un .zip.']);
    exit;
}

// Parse ZIP
$csvRaw = ediReadDecksFromZip($f['tmp_name']);
if ($csvRaw === false) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Impossible de lire le fichier ZIP.']);
    exit;
}
if ($csvRaw === '') {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Le fichier decks.csv est vide.']);
    exit;
}

$parsedDecks = ediParseDecks($csvRaw);
if (empty($parsedDecks)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Aucun deck valide trouvé dans le CSV.']);
    exit;
}

// Fetch existing deck list for name-based dedup prep (one API call).
// We also collect diagnostic info (mirrors the server-side debug) that the
// front-end shows when localStorage 'edi_debug' === '1'.
$token     = ediDeckApiToken();
$dedupWarn = false;
$nameToIds = []; // lowercase_name => [id, ...]

$incomingNames = array_column($parsedDecks, 'name');
$debug = [
    'token_present'     => $token !== '',
    'dedup_warn'        => false,
    'dedup_warn_reason' => '',
    'fetch'             => null,   // {url, http, curl_error, response_preview}
    'parsed_deck_count' => count($parsedDecks),
    'api_deck_count'    => 0,
    'incoming_names'    => array_map(function ($n) {
        return ['value' => $n, 'hex' => bin2hex($n)];
    }, $incomingNames),
    'api_names'         => [],
    'near_misses'       => [],
];

if ($token === '') {
    $dedupWarn = true;
    $debug['dedup_warn_reason'] = 'no_token';
} else {
    $fetchDebug = [];
    $existing   = ediFetchUserDecks($token, $fetchDebug);
    // Trim the raw response so the debug payload stays reasonable.
    if (isset($fetchDebug['response_preview'])) {
        $fetchDebug['response_preview'] = mb_substr((string)$fetchDebug['response_preview'], 0, 1000);
    }
    $debug['fetch'] = $fetchDebug;

    if ($existing === false) {
        $dedupWarn = true;
        $debug['dedup_warn_reason'] = 'fetch_failed';
    } else {
        $debug['api_deck_count'] = count($existing);
        $debug['api_names'] = array_map(function ($d) {
            $nm = $d['name'] ?? '';
            return ['value' => $nm, 'hex' => bin2hex($nm)];
        }, $existing);

        // Near-misses: same name case-insensitively but not byte-for-byte
        // (handled by case-insensitive matching, but useful to surface).
        $nearMisses = [];
        foreach ($incomingNames as $iname) {
            foreach ($existing as $d) {
                $aname = $d['name'] ?? '';
                if ($iname !== $aname && mb_strtolower(trim($iname)) === mb_strtolower(trim($aname))) {
                    $nearMisses[] = [
                        'incoming'     => $iname,
                        'api'          => $aname,
                        'incoming_hex' => bin2hex($iname),
                        'api_hex'      => bin2hex($aname),
                    ];
                }
            }
        }
        $debug['near_misses'] = $nearMisses;

        foreach ($existing as $d) {
            $name = mb_strtolower(trim($d['name'] ?? ''));
            $id   = $d['id'] ?? null;
            if ($name !== '' && $id !== null) {
                $nameToIds[$name][] = (string)$id;
            }
        }
    }
}
$debug['dedup_warn'] = $dedupWarn;

// Build response — attach matching_ids to each deck
$decks = [];
foreach ($parsedDecks as $deck) {
    $nameLower = mb_strtolower(trim($deck['name']));
    $decks[]   = [
        'name'         => $deck['name'],
        'format'       => $deck['format'],
        'hero'         => $deck['hero'],
        'cards'        => $deck['cards'],
        'matching_ids' => $dedupWarn ? [] : ($nameToIds[$nameLower] ?? []),
    ];
}

echo json_encode([
    'ok'            => true,
    'decks'         => $decks,
    'dedup_warn'    => $dedupWarn,
    'token_present' => $token !== '', // false → no API token; the front-end blocks before importing
    'debug'         => $debug,
], JSON_UNESCAPED_UNICODE);
