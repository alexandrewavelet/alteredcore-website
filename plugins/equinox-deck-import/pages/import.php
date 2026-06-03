<?php
require_once __DIR__ . '/../inc/functions.php';

loginRequired();

$txt = [
    'en' => [
        'page_title'   => 'Import Decks from Equinox',
        'intro'        => 'Upload the <code>.zip</code> exported by Equinox. It must contain a <code>decks.csv</code> file.',
        'file_label'   => 'Equinox export (.zip)',
        'submit'       => 'Import',
        'no_file'      => 'Please select a .zip file.',
        'zip_err'      => 'Could not read the ZIP file.',
        'no_csv'       => 'No <code>decks.csv</code> found inside the ZIP.',
        'no_ext'       => 'ZipArchive extension is not available on this server.',
        'no_decks'     => 'No valid decks found in the CSV.',
        'token_err'    => 'Could not obtain an API token. Please log out and back in.',
        'csrf_err'     => 'Invalid form token.',
        'summary'      => '%d imported, %d skipped (already exist), %d failed — out of %d deck(s).',
        'dedup_warn'   => 'Could not fetch existing decks from the API — duplicate check skipped.',
        'deck_ok'      => 'Imported',
        'deck_skip'    => 'Already exists',
        'deck_err'     => 'Failed',
        'col_deck'     => 'Deck',
        'col_status'   => 'Status',
        'col_detail'   => 'Detail',
        'q_parsing'         => 'Analysing file…',
        'q_progress'        => 'Import in progress — %1 / %2 decks',
        'q_done'            => 'Import complete',
        'q_cancelled'       => 'Import cancelled',
        'q_eta_sec'         => '~%1 sec remaining',
        'q_eta_min'         => '~%1 min remaining',
        'q_eta_min_sec'     => '~%1 min %2 sec remaining',
        'q_pause'           => 'Pause',
        'q_resume'          => 'Resume',
        'q_cancel'          => 'Cancel',
        'q_cancel_confirm'  => 'Cancel import? Decks already imported will be kept.',
        'q_dedup_warn'      => 'Could not verify duplicates — all decks will be imported.',
        'q_reset'           => 'Import another file',
        'q_current'         => 'Importing…',
        'q_failed_final'    => 'Final failure',
        'q_cancelled_item'  => 'Cancelled',
        'q_pending'         => 'Pending',
        'q_retry'           => 'Retry',
        'q_retry_left_1'    => '%1 attempt remaining',
        'q_retry_left_n'    => '%1 attempts remaining',
        'q_show_all'        => 'Show all decks (%1)',
        'q_sum_imported_1'  => '%1 imported',
        'q_sum_imported_n'  => '%1 imported',
        'q_sum_skip_1'      => '%1 already exists',
        'q_sum_skip_n'      => '%1 already exist',
        'q_sum_failed_1'    => '%1 failure',
        'q_sum_failed_n'    => '%1 failures',
        'q_sum_cancelled_1' => '%1 cancelled',
        'q_sum_cancelled_n' => '%1 cancelled',
        'q_err_network'     => 'Cannot reach server. Check your connection.',
        'q_err_generic'     => 'An error occurred.',
    ],
    'fr' => [
        'page_title'   => 'Importer des decks depuis Equinox',
        'intro'        => 'Uploadez le <code>.zip</code> exporté par Equinox. Il doit contenir un fichier <code>decks.csv</code>.',
        'file_label'   => 'Export Equinox (.zip)',
        'submit'       => 'Importer',
        'no_file'      => 'Veuillez sélectionner un fichier .zip.',
        'zip_err'      => 'Impossible de lire le fichier ZIP.',
        'no_csv'       => 'Aucun fichier <code>decks.csv</code> trouvé dans le ZIP.',
        'no_ext'       => "L'extension ZipArchive n'est pas disponible sur ce serveur.",
        'no_decks'     => 'Aucun deck valide trouvé dans le CSV.',
        'token_err'    => 'Impossible d\'obtenir un token API. Veuillez vous déconnecter et reconnecter.',
        'csrf_err'     => 'Jeton de formulaire invalide.',
        'summary'      => '%d importé(s), %d ignoré(s) (déjà existants), %d échoué(s) — sur %d deck(s).',
        'dedup_warn'   => 'Impossible de récupérer les decks existants depuis l\'API — vérification des doublons ignorée.',
        'deck_ok'      => 'Importé',
        'deck_skip'    => 'Déjà existant',
        'deck_err'     => 'Échec',
        'col_deck'     => 'Deck',
        'col_status'   => 'Statut',
        'col_detail'   => 'Détail',
        'q_parsing'         => 'Analyse du fichier…',
        'q_progress'        => 'Import en cours — %1 / %2 decks',
        'q_done'            => 'Import terminé',
        'q_cancelled'       => 'Import annulé',
        'q_eta_sec'         => '~%1 sec restantes',
        'q_eta_min'         => '~%1 min restantes',
        'q_eta_min_sec'     => '~%1 min %2 sec restantes',
        'q_pause'           => 'Pause',
        'q_resume'          => 'Reprendre',
        'q_cancel'          => 'Annuler',
        'q_cancel_confirm'  => "Annuler l'import ? Les decks déjà importés seront conservés.",
        'q_dedup_warn'      => "Impossible de vérifier les doublons — tous les decks seront importés.",
        'q_reset'           => 'Importer un autre fichier',
        'q_current'         => 'En cours…',
        'q_failed_final'    => 'Échec définitif',
        'q_cancelled_item'  => 'Annulé',
        'q_pending'         => 'En attente',
        'q_retry'           => 'Réessayer',
        'q_retry_left_1'    => '%1 essai restant',
        'q_retry_left_n'    => '%1 essais restants',
        'q_show_all'        => 'Voir tous les decks (%1)',
        'q_sum_imported_1'  => '%1 importé',
        'q_sum_imported_n'  => '%1 importés',
        'q_sum_skip_1'      => '%1 déjà existant',
        'q_sum_skip_n'      => '%1 déjà existants',
        'q_sum_failed_1'    => '%1 échec',
        'q_sum_failed_n'    => '%1 échecs',
        'q_sum_cancelled_1' => '%1 annulé',
        'q_sum_cancelled_n' => '%1 annulés',
        'q_err_network'     => "Impossible de joindre le serveur. Vérifiez votre connexion.",
        'q_err_generic'     => "Une erreur est survenue.",
    ],
][getUiLang()] ?? [];

$pageTitle    = $txt['page_title'];
$formError    = '';
$dedupWarn    = false;
$dedupDebug   = [];
$dedupDetails = []; // per-deck dedup trace: name, existing_cards, existing_hash, incoming_hash, matched
$deckRows     = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrfValid($_POST['csrf_token'] ?? '')) {
        $formError = $txt['csrf_err'];
    } else {
        $f = $_FILES['equinox_zip'] ?? null;

        if (!$f || $f['error'] !== UPLOAD_ERR_OK || $f['size'] === 0) {
            $formError = $txt['no_file'];
        } elseif (!class_exists('ZipArchive')) {
            $formError = $txt['no_ext'];
        } else {
            $csvRaw = ediReadDecksFromZip($f['tmp_name']);

            if ($csvRaw === false) {
                $formError = $txt['zip_err'];
            } elseif ($csvRaw === '') {
                $formError = $txt['no_csv'];
            } else {
                $parsedDecks = ediParseDecks($csvRaw);

                if (empty($parsedDecks)) {
                    $formError = $txt['no_decks'];
                } else {
                    $token = ediDeckApiToken();
                    if ($token === '') {
                        $formError = $txt['token_err'];
                    } else {
                        // ── Build set of existing deck hashes ─────────────────
                        $existingHashes  = [];
                        $existingByName  = []; // name → {cards_count, hash, raw_keys}
                        $existing = ediFetchUserDecks($token, $dedupDebug);
                        if ($existing === false) {
                            $dedupWarn = true;
                        } else {
                            // Only fetch full details for existing decks whose name
                            // matches an incoming deck — avoids N calls for all decks.
                            $incomingNames      = array_column($parsedDecks, 'name');
                            $incomingNamesLower = array_map('mb_strtolower', $incomingNames);

                            // ── Name comparison debug ─────────────────────────
                            $dedupDebug['api_deck_count']  = count($existing);
                            $dedupDebug['incoming_names']  = array_map(
                                fn($n) => ['value' => $n, 'hex' => bin2hex($n)],
                                $incomingNames
                            );
                            $dedupDebug['api_names'] = array_map(fn($d) => [
                                'value' => $d['name'] ?? '',
                                'hex'   => bin2hex($d['name'] ?? ''),
                            ], $existing);

                            // Flag near-misses: would match case-insensitively but not strictly
                            $nearMisses = [];
                            foreach ($incomingNames as $iname) {
                                foreach ($existing as $d) {
                                    $aname = $d['name'] ?? '';
                                    if ($iname !== $aname && mb_strtolower(trim($iname)) === mb_strtolower(trim($aname))) {
                                        // This near-miss is now handled by case-insensitive matching
                                        $nearMisses[] = [
                                            'incoming'     => $iname,
                                            'api'          => $aname,
                                            'incoming_hex' => bin2hex($iname),
                                            'api_hex'      => bin2hex($aname),
                                        ];
                                    }
                                }
                            }
                            $dedupDebug['near_misses'] = $nearMisses;
                            // ─────────────────────────────────────────────────

                            // Collect ALL API decks whose name matches any incoming deck, fetch in parallel.
                            // Keyed by ID — natural dedup if the same ID appears multiple times.
                            // Case-insensitive: API lowercases names on store.
                            $toFetch = []; // id → summary deck object
                            foreach ($existing as $d) {
                                $dname      = $d['name'] ?? '';
                                $dnameLower = mb_strtolower($dname);
                                if (isset($d['id']) && in_array($dnameLower, $incomingNamesLower, true)) {
                                    $toFetch[(string)$d['id']] = $d;
                                }
                            }

                            $fetchErrors = [];
                            $dedupDebug['to_fetch_count'] = count($toFetch);
                            $fullDecks = ediFetchDecksByIds(array_keys($toFetch), $token, $fetchErrors);
                            $dedupDebug['fetched_count']  = count($fullDecks);
                            // Capture first 3 failures for display
                            $dedupDebug['fetch_errors'] = array_slice($fetchErrors, 0, 3, true);

                            foreach ($toFetch as $id => $d) {
                                $dname = $d['name'] ?? '';
                                $full  = $fullDecks[$id] ?? null;
                                if ($full === null) continue;

                                $apiCards = $full['deckCards'] ?? $full['cards'] ?? [];
                                $h        = ediDeckContentHash($dname, $apiCards);
                                $existingHashes[]                       = $h;
                                $existingByName[mb_strtolower($dname)]  = [
                                    'cards_count' => count($apiCards),
                                    'hash'        => $h,
                                    'keys'        => implode(', ', array_keys($d)),
                                ];
                            }
                        }

                        // ── Import loop ───────────────────────────────────────
                        $deckRows = [];

                        foreach ($parsedDecks as $deck) {
                            $normalizedCards = ediNormalizeDeckCards($deck);
                            $incomingHash    = ediDeckContentHash($deck['name'], $normalizedCards);
                            $existingEntry   = $existingByName[mb_strtolower($deck['name'])] ?? null;
                            $matched         = !$dedupWarn && in_array($incomingHash, $existingHashes, true);

                            $dedupDetails[] = [
                                'name'           => $deck['name'] ?: '?',
                                'incoming_cards' => count($normalizedCards),
                                'incoming_hash'  => $incomingHash,
                                'existing_cards' => $existingEntry['cards_count'] ?? null,
                                'existing_hash'  => $existingEntry['hash']        ?? null,
                                'existing_keys'  => $existingEntry['keys']        ?? null,
                                'matched'        => $matched,
                            ];

                            if ($matched) {
                                $deckRows[] = [
                                    'name'    => $deck['name'] ?: '?',
                                    'status'  => 'skip',
                                    'error'   => '',
                                ];
                                continue;
                            }

                            $result = ediImportDeck($deck, $token);

                            $deckRows[] = [
                                'name'   => $deck['name'] ?: '?',
                                'status' => $result['ok'] ? 'ok' : 'err',
                                'error'  => $result['error'],
                            ];
                        }
                    }
                }
            }
        }
    }
}

$countOk   = $deckRows !== null ? count(array_filter($deckRows, fn($r) => $r['status'] === 'ok'))   : 0;
$countSkip = $deckRows !== null ? count(array_filter($deckRows, fn($r) => $r['status'] === 'skip')) : 0;
$countErr  = $deckRows !== null ? count(array_filter($deckRows, fn($r) => $r['status'] === 'err'))  : 0;
$total     = $deckRows !== null ? count($deckRows) : 0;

$summaryType = $countErr === 0 ? 'success' : ($countOk > 0 ? 'warning' : 'danger');
$summaryIcon = $countErr === 0 ? 'check'   : 'triangle-exclamation';
?>
<script>
var SITE_BASE = <?= json_encode(defined('BASE_URL') ? BASE_URL : '', JSON_UNESCAPED_SLASHES) ?>;
var EDI_CSRF  = <?= json_encode(csrfToken()) ?>;
var EDI_TXT   = <?= json_encode($txt, JSON_UNESCAPED_UNICODE) ?>;
</script>
<div class="container py-4" style="max-width:680px">

    <div class="d-flex align-items-center justify-content-between mb-4">
        <div class="section-title mb-0"><span><?= h($pageTitle) ?></span></div>
    </div>

    <?php if ($formError): ?>
    <div class="alert alert-danger py-2 mb-4">
        <i class="fa-solid fa-circle-exclamation me-2"></i><?= $formError ?>
    </div>
    <?php endif; ?>

    <?php if ($deckRows !== null && $formError === ''): ?>

        <?php if ($dedupWarn): ?>
        <div class="alert alert-warning py-2 mb-3 small">
            <i class="fa-solid fa-triangle-exclamation me-2"></i><?= h($txt['dedup_warn']) ?>
            <?php if (!empty($dedupDebug)): ?>
            <details class="mt-2 edi-debug" style="display:none">
                <summary class="small" style="cursor:pointer;opacity:.8">Debug info (fetch failed)</summary>
                <table class="table table-sm mb-0 mt-2 small font-monospace">
                    <tr><td class="text-muted" style="width:120px">URL</td>
                        <td><?= h($dedupDebug['url'] ?? '') ?></td></tr>
                    <tr><td class="text-muted">HTTP code</td>
                        <td><?= h((string)($dedupDebug['http'] ?? 0)) ?></td></tr>
                    <?php if (!empty($dedupDebug['curl_error'])): ?>
                    <tr><td class="text-muted">cURL error</td>
                        <td class="text-danger"><?= h($dedupDebug['curl_error']) ?></td></tr>
                    <?php endif; ?>
                    <?php if (!empty($dedupDebug['response_preview'])): ?>
                    <tr><td class="text-muted align-top">Response</td>
                        <td><pre class="small bg-light p-2 rounded overflow-auto mb-0" style="max-height:300px;white-space:pre-wrap;word-break:break-all"><?= h($dedupDebug['response_preview']) ?></pre></td></tr>
                    <?php endif; ?>
                </table>
            </details>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <?php if (!empty($dedupDebug['api_names']) || !empty($dedupDebug['incoming_names'])): ?>
        <details class="mb-3 edi-debug" style="display:none">
            <summary class="small text-muted user-select-none" style="cursor:pointer">
                <i class="fa-solid fa-magnifying-glass me-1"></i>Name matching debug
                (<?= count($dedupDebug['incoming_names'] ?? []) ?> incoming,
                 <?= $dedupDebug['api_deck_count'] ?? 0 ?> in API
                <?php if (!empty($dedupDebug['near_misses'])): ?>
                — <span class="text-warning-emphasis"><?= count($dedupDebug['near_misses']) ?> near-miss(es)</span>
                <?php endif; ?>)
            </summary>
            <div class="card-altered mt-2 p-3 small font-monospace overflow-auto">

                <?php if (!empty($dedupDebug['near_misses'])): ?>
                <div class="alert alert-warning p-2 rounded mb-3">
                    <strong>Near-misses</strong> — same name but strict comparison fails (likely cause):
                    <?php foreach ($dedupDebug['near_misses'] as $nm): ?>
                    <div class="mt-2">
                        <div>CSV&nbsp;&nbsp;: "<span class="text-primary"><?= h($nm['incoming']) ?></span>" &nbsp;hex: <?= h($nm['incoming_hex']) ?></div>
                        <div>API&nbsp;&nbsp;: "<span class="text-danger"><?= h($nm['api']) ?></span>" &nbsp;hex: <?= h($nm['api_hex']) ?></div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>

                <?php if (isset($dedupDebug['to_fetch_count'])): ?>
                <div class="mb-2 text-muted">
                    Detail fetches: <?= $dedupDebug['to_fetch_count'] ?> sent,
                    <?= $dedupDebug['fetched_count'] ?? 0 ?> received
                    <?php if (($dedupDebug['fetched_count'] ?? 0) < $dedupDebug['to_fetch_count']): ?>
                    — <span class="text-danger">some fetches failed</span>
                    <?php endif; ?>
                </div>
                <?php if (!empty($dedupDebug['fetch_errors'])): ?>
                <div class="alert alert-danger p-2 rounded mb-3">
                    <strong>Failed detail fetch (first <?= count($dedupDebug['fetch_errors']) ?>):</strong>
                    <?php foreach ($dedupDebug['fetch_errors'] as $eid => $fe): ?>
                    <div class="mt-2 border-top pt-1">
                        <div>ID: <code><?= h($eid) ?></code></div>
                        <div>URL: <code><?= h($fe['url'] ?? '') ?></code></div>
                        <div>HTTP: <strong><?= (int)$fe['http'] ?></strong>
                            <?php if (!empty($fe['curl_error'])): ?> — cURL: <?= h($fe['curl_error']) ?><?php endif; ?>
                        </div>
                        <?php if (!empty($fe['preview'])): ?>
                        <div>Response: <code class="text-break"><?= h($fe['preview']) ?></code></div>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
                <?php endif; ?>

                <div class="row g-3">
                    <div class="col">
                        <div class="text-muted mb-1">Incoming (from CSV)</div>
                        <?php foreach ($dedupDebug['incoming_names'] ?? [] as $n): ?>
                        <div class="py-1">"<?= h($n['value']) ?>"
                            <span class="text-muted opacity-50 small"><?= h($n['hex']) ?></span>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <div class="col">
                        <div class="text-muted mb-1">API deck names</div>
                        <?php foreach ($dedupDebug['api_names'] ?? [] as $n): ?>
                        <?php $strictMatch = in_array($n['value'], array_column($dedupDebug['incoming_names'] ?? [], 'value'), true); ?>
                        <div class="py-1 <?= $strictMatch ? 'text-success' : '' ?>">
                            "<?= h($n['value']) ?>"
                            <span class="text-muted opacity-50 small"><?= h($n['hex']) ?></span>
                            <?= $strictMatch ? ' ✓' : '' ?>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </details>
        <?php endif; ?>

        <div class="alert alert-<?= $summaryType ?> py-2 mb-3">
            <i class="fa-solid fa-<?= $summaryIcon ?> me-2"></i>
            <?= h(sprintf($txt['summary'], $countOk, $countSkip, $countErr, $total)) ?>
        </div>

        <div class="card-altered mb-4">
            <table class="table table-sm table-altered mb-0">
                <thead>
                    <tr>
                        <th style="width:58%"><?= h($txt['col_deck']) ?></th>
                        <th style="width:22%"><?= h($txt['col_status']) ?></th>
                        <th><?= h($txt['col_detail']) ?></th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($deckRows as $row): ?>
                    <tr>
                        <td><?= h($row['name']) ?></td>
                        <td>
                            <?php if ($row['status'] === 'ok'): ?>
                            <span class="badge bg-success"><?= h($txt['deck_ok']) ?></span>
                            <?php elseif ($row['status'] === 'skip'): ?>
                            <span class="badge bg-secondary"><?= h($txt['deck_skip']) ?></span>
                            <?php else: ?>
                            <span class="badge bg-danger"><?= h($txt['deck_err']) ?></span>
                            <?php endif; ?>
                        </td>
                        <td class="text-muted small"><?= $row['status'] === 'err' ? h($row['error']) : '' ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <?php if (!empty($dedupDetails)): ?>
        <details class="mb-4 edi-debug" style="display:none">
            <summary class="small text-muted user-select-none" style="cursor:pointer">
                <i class="fa-solid fa-bug me-1"></i>Dedup debug (<?= count($dedupDetails) ?> deck(s) — <?= count(array_filter($dedupDetails, fn($d) => $d['existing_hash'] !== null)) ?> found in API)
            </summary>
            <div class="card-altered mt-2 p-3 small font-monospace overflow-auto">
            <?php foreach ($dedupDetails as $d): ?>
                <div class="mb-3 pb-3 border-bottom">
                    <strong><?= h($d['name']) ?></strong>
                    <?php if ($d['matched']): ?>
                        <span class="badge bg-secondary ms-2">matched → skip</span>
                    <?php else: ?>
                        <span class="badge bg-warning text-dark ms-2">no match → imported</span>
                    <?php endif; ?>
                    <table class="table table-sm table-borderless mt-1 mb-0">
                        <tr>
                            <td class="text-muted" style="width:130px">Incoming cards</td>
                            <td><?= (int)$d['incoming_cards'] ?></td>
                        </tr>
                        <tr>
                            <td class="text-muted">Incoming hash</td>
                            <td class="text-primary"><?= h($d['incoming_hash']) ?></td>
                        </tr>
                        <?php if ($d['existing_hash'] !== null): ?>
                        <tr>
                            <td class="text-muted">Existing cards</td>
                            <td><?= (int)$d['existing_cards'] ?><?= $d['existing_cards'] === 0 ? ' <span class="text-danger">(API returned no cards — list endpoint may omit deckCards)</span>' : '' ?></td>
                        </tr>
                        <tr>
                            <td class="text-muted">Existing hash</td>
                            <td class="<?= $d['existing_hash'] === $d['incoming_hash'] ? 'text-success' : 'text-danger' ?>"><?= h($d['existing_hash']) ?></td>
                        </tr>
                        <tr>
                            <td class="text-muted">API fields</td>
                            <td class="text-muted"><?= h($d['existing_keys'] ?? '') ?></td>
                        </tr>
                        <?php else: ?>
                        <tr>
                            <td class="text-muted">Existing</td>
                            <td class="text-muted">not found in API response by name</td>
                        </tr>
                        <?php endif; ?>
                    </table>
                </div>
            <?php endforeach; ?>
            </div>
        </details>
        <?php endif; ?>

    <?php endif; ?>

<script>
(function () {
    // Debug panels (.edi-debug) are hidden by default. To reveal them, run in the
    // browser console:  localStorage.setItem('edi_debug', '1')  then reload.
    // (Set it back to '0' to hide again.)
    var stored = localStorage.getItem('edi_debug') === '1';

    function applyDebug(show) {
        document.querySelectorAll('.edi-debug').forEach(function (el) {
            el.style.display = show ? '' : 'none';
        });
    }

    applyDebug(stored);

    document.addEventListener('DOMContentLoaded', function () {
        applyDebug(stored);

        // When running standalone under the dev toolbar, add a quick toggle there.
        var bar = document.getElementById('_devbar');
        if (!bar) return;

        var sep = document.createElement('span');
        sep.style.cssText = 'color:#585b70';
        sep.textContent = '|';
        bar.appendChild(sep);

        var label = document.createElement('label');
        label.style.cssText = 'display:flex;align-items:center;gap:4px;cursor:pointer;color:#cba6f7';

        var cb = document.createElement('input');
        cb.type = 'checkbox';
        cb.checked = stored;
        cb.style.cssText = 'accent-color:#cba6f7;cursor:pointer';
        cb.addEventListener('change', function () {
            localStorage.setItem('edi_debug', cb.checked ? '1' : '0');
            applyDebug(cb.checked);
        });

        label.appendChild(cb);
        label.appendChild(document.createTextNode(' debug'));
        bar.appendChild(label);
    });
})();
</script>

    <div class="card-altered p-4">
        <p class="text-muted small mb-4"><?= $txt['intro'] ?></p>

        <form method="post" enctype="multipart/form-data">
            <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">

            <div class="mb-4">
                <label class="form-label fw-semibold">
                    <i class="fa-solid fa-file-zipper me-1"></i><?= h($txt['file_label']) ?>
                </label>
                <input type="file" name="equinox_zip" class="form-control" accept=".zip,application/zip">
            </div>

            <button type="submit" class="btn btn-primary-altered btn-sm">
                <i class="fa-solid fa-file-import me-1"></i><?= h($txt['submit']) ?>
            </button>
        </form>
    </div>

</div>
