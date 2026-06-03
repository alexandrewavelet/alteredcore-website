<?php
// API endpoint: import one deck (dedup-check, then create via the Deck API).
// Thin controller — wires dependencies and runs the ImportDeck use case.
require_once __DIR__ . '/../inc/bootstrap.php';

use AlteredCore\EquinoxDeckImport\Application\UseCase\ImportDeck;
use AlteredCore\EquinoxDeckImport\Http\Guards;
use AlteredCore\EquinoxDeckImport\Http\Json;
use AlteredCore\EquinoxDeckImport\Infrastructure\CurlDeckApiClient;
use AlteredCore\EquinoxDeckImport\Infrastructure\KeycloakTokenProvider;
use AlteredCore\EquinoxDeckImport\Presentation\Translations;

$lang = function_exists('getUiLang') ? getUiLang() : 'en';
$msg = Translations::import($lang);

if (!Guards::isLoggedIn()) {
    Json::send(['ok' => false, 'status' => 'error', 'error_code' => 'auth', 'error_msg' => $msg['unauthorized']], 401);
    return;
}

$body = json_decode(file_get_contents('php://input'), true);
if (!is_array($body)) {
    Json::send(['ok' => false, 'status' => 'error', 'error_code' => 'invalid_body', 'error_msg' => $msg['invalid_body']], 400);
    return;
}
if (!Guards::csrfValid($body['csrf_token'] ?? null)) {
    Json::send(['ok' => false, 'status' => 'error', 'error_code' => 'csrf', 'error_msg' => $msg['csrf']], 403);
    return;
}

$tokens = new KeycloakTokenProvider();
$useCase = new ImportDeck(new CurlDeckApiClient($tokens), $tokens);

$result = $useCase->execute($body, $msg, !empty($body['debug']));
Json::send($result->toArray(), $result->status);
