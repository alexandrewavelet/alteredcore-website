<?php
require_once dirname(__DIR__) . '/inc/functions.php';
header('Content-Type: application/json');

// Auth guard
if (!function_exists('kcIsLoggedIn') || !kcIsLoggedIn()) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'status' => 'error',
                      'error_code' => 'auth', 'error_msg' => 'Non autorisé.']);
    exit;
}

// Parse JSON body
$body = json_decode(file_get_contents('php://input'), true);
if (!is_array($body)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'status' => 'error',
                      'error_code' => 'invalid_body', 'error_msg' => 'Corps de requête invalide.']);
    exit;
}

// CSRF guard
if (!function_exists('csrfValid') || !csrfValid($body['csrf_token'] ?? '')) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'status' => 'error',
                      'error_code' => 'csrf', 'error_msg' => 'Jeton de formulaire invalide.']);
    exit;
}

// Validate required fields
$name        = trim($body['name']        ?? '');
$format      = trim($body['format']      ?? 'standard');
$hero        = trim($body['hero']        ?? '');
$cards       = $body['cards']            ?? [];
$matchingIds = $body['matching_ids']     ?? [];

if ($name === '' || !is_array($cards) || empty($cards)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'status' => 'error',
                      'error_code' => 'invalid_deck',
                      'error_msg'  => 'Ce deck ne contient aucune carte valide et ne peut pas être importé.']);
    exit;
}

// Validate card shape before forwarding to external API
foreach ($cards as $c) {
    $ref = $c['cardReference'] ?? '';
    $qty = (int)($c['quantity'] ?? 0);
    if (!is_array($c) || !preg_match('/^ALT_[A-Z0-9_]+$/', (string)$ref) || $qty < 1 || $qty > 99) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'status' => 'error',
                          'error_code' => 'invalid_card',
                          'error_msg'  => 'Ce deck contient une référence de carte invalide.']);
        exit;
    }
}

// Token guard
$token = ediDeckApiToken();
if ($token === '') {
    http_response_code(401);
    echo json_encode(['ok' => false, 'status' => 'error',
                      'error_code' => 'no_token',
                      'error_msg'  => 'Session expirée — veuillez vous reconnecter.']);
    exit;
}

$deck            = ['name' => $name, 'format' => $format, 'hero' => $hero, 'cards' => $cards];
$normalizedCards = ediNormalizeDeckCards($deck);
$incomingHash    = ediDeckContentHash($name, $normalizedCards);

// Diagnostic payload — surfaced by the front-end when localStorage 'edi_debug' === '1'.
$debug = [
    'request' => [
        'name'             => $name,
        'format'           => $format,
        'hero'             => $hero,
        'card_count'       => count($cards),
        'normalized_count' => count($normalizedCards),
        'incoming_hash'    => $incomingHash,
    ],
    'dedup' => [
        'matching_ids' => array_values(array_map('strval', (array)$matchingIds)),
        'checked'      => [],   // [{id, name, cards_count, api_hash, matched}]
        'fetch_errors' => [],
        'decision'     => 'import',
    ],
    'import' => null,           // {http, response_preview, error}
];

// Dedup check: fetch details only for matching IDs (max ~2 API calls)
if (!empty($matchingIds) && is_array($matchingIds)) {
    $safeIds   = array_values(array_filter(array_map('strval', $matchingIds)));
    $errors    = [];
    $fullDecks = ediFetchDecksByIds($safeIds, $token, $errors);
    $debug['dedup']['fetch_errors'] = array_slice($errors, 0, 3, true);

    foreach ($fullDecks as $fid => $full) {
        $apiCards = $full['deckCards'] ?? $full['cards'] ?? [];
        $apiHash  = ediDeckContentHash($full['name'] ?? $name, $apiCards);
        $isMatch  = ($apiHash === $incomingHash);

        $debug['dedup']['checked'][] = [
            'id'          => (string)$fid,
            'name'        => $full['name'] ?? '',
            'cards_count' => count($apiCards),
            'api_hash'    => $apiHash,
            'matched'     => $isMatch,
        ];

        if ($isMatch) {
            $debug['dedup']['decision'] = 'skip';
            echo json_encode(['ok' => true, 'status' => 'skip', 'debug' => $debug], JSON_UNESCAPED_UNICODE);
            exit;
        }
    }
}

// Import
$result = ediImportDeck($deck, $token);
$debug['import'] = [
    'http'             => $result['http'] ?? 0,
    'response_preview' => $result['response_preview'] ?? '',
    'error'            => $result['error'] ?? '',
];

if ($result['ok']) {
    echo json_encode(['ok' => true, 'status' => 'imported', 'id' => $result['id'], 'debug' => $debug], JSON_UNESCAPED_UNICODE);
    exit;
}

$httpCode = $result['http'] ?? 0;
$apiError = $result['error'] ?? '';
if ($apiError !== '') {
    error_log(sprintf('[equinox-deck-import] import failed HTTP %d for deck "%s": %s', $httpCode, $name, $apiError));
}
$errorMsg = ediMapErrorCode($httpCode, $httpCode === 0 ? $apiError : '');
echo json_encode([
    'ok'         => false,
    'status'     => 'error',
    'error_code' => 'http_' . $httpCode,
    'error_msg'  => $errorMsg,
    'debug'      => $debug,
], JSON_UNESCAPED_UNICODE);
exit;
