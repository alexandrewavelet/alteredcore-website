<?php

/**
 * Returns the current user's Altered Deck API bearer token, or '' if unavailable.
 *
 * Self-contained: it calls the CORE Keycloak helper kc_get_access_token()
 * (includes/func.keycloak.php, always loaded by includes/functions.php) directly,
 * so this plugin does not depend on any other plugin. This mirrors what
 * core-altered-cards' deckApiToken() does, without the cross-plugin coupling.
 * Returns '' when the user is not logged in via Keycloak or no token can be
 * obtained — callers surface a clear "session expired / please log in" message.
 */
function ediDeckApiToken(): string
{
    if (!function_exists('kcIsLoggedIn') || !kcIsLoggedIn()) {
        return '';
    }
    if (!function_exists('kc_get_access_token')) {
        return '';
    }
    $userId = (int)($_SESSION['user_id'] ?? 0);
    if ($userId <= 0) {
        return '';
    }
    $token = kc_get_access_token($userId);
    return (is_string($token) && $token !== '') ? $token : '';
}

/**
 * Parse Equinox decks.csv → array of decks.
 *
 * Each deck: ['name' => string, 'format' => string, 'hero' => string, 'cards' => [['cardReference' => string, 'quantity' => int]]]
 *
 * Uses str_getcsv to correctly handle quoted fields (e.g. "Subhash & Marmo").
 */
function ediParseDecks(string $raw): array
{
    $raw   = ltrim($raw, "\xEF\xBB\xBF");
    $lines = explode("\n", str_replace("\r", '', $raw));
    $decks = [];
    $order = [];
    $first = true;

    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '') continue;
        if ($first) { $first = false; continue; }

        $cols = str_getcsv($line, ';', '"');
        if (count($cols) < 8) continue;

        $did  = trim($cols[0]);
        if ($did === '') continue;

        $dname = trim($cols[1]);
        $dfmt  = strtolower(trim($cols[2]));
        $hero  = strtoupper(trim($cols[3]));
        $cref  = strtoupper(trim($cols[5]));
        $cqty  = (int)trim($cols[7]);

        if (!isset($decks[$did])) {
            $decks[$did] = ['name' => $dname, 'format' => $dfmt, 'hero' => $hero, 'cards' => []];
            $order[]     = $did;
        }

        if ($cref !== '' && $cqty > 0 && preg_match('/^ALT_[A-Z0-9_]+$/', $cref)) {
            // Accumulate by cardReference (keyed map) so duplicate rows for the
            // same card sum their quantities instead of producing duplicate
            // deckCards entries the Deck API may reject. Flattened to a list below.
            if (isset($decks[$did]['cards'][$cref])) {
                $decks[$did]['cards'][$cref] += $cqty;
            } else {
                $decks[$did]['cards'][$cref] = $cqty;
            }
        }
    }

    $result = [];
    foreach ($order as $id) {
        $deck  = $decks[$id];
        $cards = [];
        foreach ($deck['cards'] as $ref => $qty) {
            $cards[] = ['cardReference' => $ref, 'quantity' => $qty];
        }
        $deck['cards'] = $cards;
        $result[]      = $deck;
    }
    return $result;
}

/**
 * Extract decks.csv content from a ZIP file on disk.
 * Searches at any nesting depth (e.g. clear/decks.csv).
 * Returns the raw CSV string, or false on failure.
 *
 * @return string|false
 */
function ediReadDecksFromZip(string $zipPath)
{
    if (!class_exists('ZipArchive')) {
        return false;
    }
    $zip = new ZipArchive();
    if ($zip->open($zipPath) !== true) {
        return false;
    }

    $csv = false;
    for ($i = 0; $i < $zip->numFiles; $i++) {
        if (basename($zip->getNameIndex($i)) === 'decks.csv') {
            $csv = $zip->getFromIndex($i);
            break;
        }
    }

    $zip->close();
    return $csv;
}

/**
 * Normalize a parsed deck's card list: add the hero as a card if not already present.
 * Returns the final card array as it will be sent to the API.
 */
function ediNormalizeDeckCards(array $deck): array
{
    $cards = $deck['cards'];
    $hero  = $deck['hero'] ?? '';

    if ($hero !== '' && preg_match('/^ALT_[A-Z0-9_]+$/', $hero)) {
        $found = false;
        foreach ($cards as $c) {
            if ($c['cardReference'] === $hero) { $found = true; break; }
        }
        if (!$found) {
            array_unshift($cards, ['cardReference' => $hero, 'quantity' => 1]);
        }
    }

    return $cards;
}

/**
 * Compute a content hash for duplicate detection.
 *
 * Hash covers: lowercased deck name + sorted {cardReference => quantity} map.
 * Works on both local parsed decks (after ediNormalizeDeckCards) and API response cards.
 */
function ediDeckContentHash(string $name, array $deckCards): string
{
    $map = [];
    foreach ($deckCards as $c) {
        $ref = strtoupper(trim($c['cardReference'] ?? ''));
        if ($ref !== '') {
            $map[$ref] = ($map[$ref] ?? 0) + (int)($c['quantity'] ?? 1);
        }
    }
    ksort($map);
    return md5(mb_strtolower(trim($name)) . '|' . json_encode($map));
}

/**
 * Fetch the authenticated user's existing decks from the API.
 * Returns an array of deck objects, or false on error.
 * Populates $debug with diagnostic info on failure.
 *
 * @param array<string,mixed> $debug  Filled with: url, http, curl_error, response_preview
 * @return array|false
 */
function ediFetchUserDecks(string $token, array &$debug = [])
{
    $url    = DECKS_API_URL . '/api/decks';
    $debug  = ['url' => $url, 'http' => 0, 'curl_error' => '', 'response_preview' => ''];

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER     => [
            'Accept: application/json',
            'Authorization: Bearer ' . $token,
        ],
        CURLOPT_TIMEOUT        => 20,
        CURLOPT_SSL_VERIFYPEER => !(defined('DEV_MODE') && DEV_MODE),
        CURLOPT_SSL_VERIFYHOST => (defined('DEV_MODE') && DEV_MODE) ? 0 : 2,
    ]);

    $resp     = curl_exec($ch);
    $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlErr  = curl_error($ch);
    unset($ch);

    $debug['http']             = $httpCode;
    $debug['curl_error']       = $curlErr;
    $debug['response_preview'] = $resp !== false ? $resp : '';

    if ($resp === false || $curlErr !== '') {
        return false;
    }
    if ($httpCode !== 200) {
        return false;
    }

    $data = json_decode($resp, true);
    if (!is_array($data)) return false;

    // Handle both flat arrays and wrapped responses: {items: [...]} / {decks: [...]}
    foreach (['items', 'decks', 'data'] as $key) {
        if (isset($data[$key]) && is_array($data[$key])) return $data[$key];
    }
    return $data;
}

/**
 * Fetch full details (including deckCards) for multiple decks in parallel.
 *
 * @param  string[] $ids      Deck UUIDs to fetch
 * @param  array    $errors   Filled with per-id failure info: {http, curl_error, preview}
 * @return array<string, array>  Map of id → full deck object (missing = fetch failed)
 */
function ediFetchDecksByIds(array $ids, string $token, array &$errors = []): array
{
    if (empty($ids)) return [];

    $headers = [
        'Accept: application/json',
        'Authorization: Bearer ' . $token,
    ];

    $mh      = curl_multi_init();
    $handles = [];

    foreach ($ids as $id) {
        $ch = curl_init(DECKS_API_URL . '/api/decks/' . rawurlencode($id));
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_TIMEOUT        => 20,
            CURLOPT_SSL_VERIFYPEER => !(defined('DEV_MODE') && DEV_MODE),
            CURLOPT_SSL_VERIFYHOST => (defined('DEV_MODE') && DEV_MODE) ? 0 : 2,
        ]);
        curl_multi_add_handle($mh, $ch);
        $handles[$id] = $ch;
    }

    $running = null;
    do {
        curl_multi_exec($mh, $running);
        if ($running > 0) {
            // Block until there is activity; usleep avoids a 100% CPU busy-spin
            // when curl_multi_select returns -1 (no descriptors ready / select error).
            if (curl_multi_select($mh) === -1) {
                usleep(1000);
            }
        }
    } while ($running > 0);

    $results = [];
    $errors  = [];
    foreach ($handles as $id => $ch) {
        $resp      = curl_multi_getcontent($ch);
        $httpCode  = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErr   = curl_error($ch);
        $effectiveUrl = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
        curl_multi_remove_handle($mh, $ch);

        if ($resp !== false && $httpCode === 200) {
            $data = json_decode($resp, true);
            if (is_array($data)) {
                $results[$id] = $data;
            } else {
                $errors[$id] = ['http' => $httpCode, 'curl_error' => $curlErr,
                                'url' => $effectiveUrl, 'preview' => mb_substr((string)$resp, 0, 200)];
            }
        } else {
            $errors[$id] = ['http' => $httpCode, 'curl_error' => $curlErr,
                            'url' => $effectiveUrl, 'preview' => mb_substr((string)$resp, 0, 200)];
        }
    }

    curl_multi_close($mh);
    return $results;
}

/**
 * POST one deck to the Altered Deck API.
 * Returns ['ok' => bool, 'id' => string|null, 'http' => int, 'error' => string].
 */
function ediImportDeck(array $deck, string $token): array
{
    $cards = ediNormalizeDeckCards($deck);

    if (empty($cards)) {
        return ['ok' => false, 'id' => null, 'http' => 0, 'error' => 'no valid cards', 'response_preview' => ''];
    }

    $payload = json_encode([
        'name'      => $deck['name'] ?: 'Imported deck',
        'format'    => $deck['format'] ?: 'standard',
        'isPublic'  => false,
        'isDraft'   => false,
        'deckCards' => $cards,
    ], JSON_UNESCAPED_UNICODE);

    $ch = curl_init(DECKS_API_URL . '/api/decks');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST  => 'POST',
        CURLOPT_HTTPHEADER     => [
            'Content-Type: application/json',
            'Accept: application/json',
            'Authorization: Bearer ' . $token,
        ],
        CURLOPT_POSTFIELDS     => $payload,
        CURLOPT_TIMEOUT        => 20,
        CURLOPT_SSL_VERIFYPEER => !(defined('DEV_MODE') && DEV_MODE),
        CURLOPT_SSL_VERIFYHOST => (defined('DEV_MODE') && DEV_MODE) ? 0 : 2,
    ]);

    $resp     = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlErr  = curl_error($ch);
    unset($ch);

    if ($resp === false || $curlErr !== '') {
        return ['ok' => false, 'id' => null, 'http' => 0, 'error' => $curlErr ?: 'curl error', 'response_preview' => ''];
    }

    if ($httpCode >= 200 && $httpCode < 300) {
        $data = json_decode($resp, true);
        return ['ok' => true, 'id' => $data['id'] ?? null, 'http' => $httpCode, 'error' => '',
                'response_preview' => mb_substr((string)$resp, 0, 800)];
    }

    $body = json_decode($resp, true);
    $msg  = $body['message'] ?? $body['error'] ?? ('HTTP ' . $httpCode);
    return ['ok' => false, 'id' => null, 'http' => $httpCode, 'error' => $msg,
            'response_preview' => mb_substr((string)$resp, 0, 800)];
}

/**
 * Map an HTTP response code / cURL error to a user-readable message.
 * Never exposes internal API details.
 */
function ediMapErrorCode(int $httpCode, string $curlErr): string
{
    if ($curlErr !== '' || $httpCode === 0) {
        return 'Impossible de joindre le serveur. Vérifiez votre connexion.';
    }
    if ($httpCode === 401) {
        return 'Session expirée — veuillez vous reconnecter.';
    }
    if ($httpCode === 429) {
        return 'Trop de requêtes. Réessayez dans quelques instants.';
    }
    if ($httpCode === 400) {
        return 'Ce deck contient des données que le serveur n\'accepte pas.';
    }
    if (in_array($httpCode, [502, 503, 504], true)) {
        return 'Serveur temporairement indisponible.';
    }
    if ($httpCode >= 500) {
        return 'Erreur serveur inattendue.';
    }
    return 'Une erreur est survenue lors de l\'import de ce deck.';
}
