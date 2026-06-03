<?php
// API endpoint: parse an uploaded Equinox ZIP into decks + dedup candidates.
// Thin controller — wires dependencies and runs the ParseUpload use case.
require_once __DIR__ . '/../inc/bootstrap.php';

use AlteredCore\EquinoxDeckImport\Application\UseCase\ParseUpload;
use AlteredCore\EquinoxDeckImport\Domain\DeckParser;
use AlteredCore\EquinoxDeckImport\Http\Guards;
use AlteredCore\EquinoxDeckImport\Http\Json;
use AlteredCore\EquinoxDeckImport\Infrastructure\CurlDeckApiClient;
use AlteredCore\EquinoxDeckImport\Infrastructure\KeycloakTokenProvider;
use AlteredCore\EquinoxDeckImport\Infrastructure\ZipDeckCsvReader;
use AlteredCore\EquinoxDeckImport\Presentation\Translations;

$lang = function_exists('getUiLang') ? getUiLang() : 'en';
$msg = Translations::parse($lang);

if (!Guards::isLoggedIn()) {
    Json::send(['ok' => false, 'error' => $msg['unauthorized']], 401);
    return;
}
if (!Guards::csrfValid($_POST['csrf_token'] ?? null)) {
    Json::send(['ok' => false, 'error' => $msg['csrf']], 403);
    return;
}

$tokens = new KeycloakTokenProvider();
$useCase = new ParseUpload(new ZipDeckCsvReader(), new DeckParser(), new CurlDeckApiClient($tokens), $tokens);

$result = $useCase->execute($_FILES['equinox_zip'] ?? null, $msg, !empty($_POST['debug']));
Json::send($result->toArray(), $result->status);
