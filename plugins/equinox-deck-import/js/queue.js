// Equinox Deck Import — front-end import queue.
// Loaded automatically on the plugin's own page (declared in plugin.json under
// assets.js). Handles large imports (>= THRESHOLD decks) one-by-one against the
// /papi/equinox-deck-import/import-deck endpoint, with retries, pause/cancel,
// ETA and a compact view for long lists. Small imports fall through to the
// standard server-side PHP POST handled by pages/import.php.
(function () {
    'use strict';

    var SITE_BASE   = (typeof window.SITE_BASE !== 'undefined') ? window.SITE_BASE : '';
    var EDI_CSRF    = (typeof window.EDI_CSRF  !== 'undefined') ? window.EDI_CSRF  : '';
    var THRESHOLD    = 30;
    var MAX_RETRIES  = 3;
    var ETA_WINDOW   = 5;
    var RATE_PER_SEC = 3;                              // client-side cap: max import starts per second
    var MIN_INTERVAL = Math.ceil(1000 / RATE_PER_SEC); // min ms between two consecutive import starts (~334ms)

    // ── i18n ──────────────────────────────────────────────────────────────────
    var T = (typeof window.EDI_TXT !== 'undefined') ? window.EDI_TXT : {};

    function t(key) {
        return T[key] !== undefined ? T[key] : key;
    }

    function tf(key, a, b) {
        var s = t(key);
        if (a !== undefined) s = s.replace('%1', a);
        if (b !== undefined) s = s.replace('%2', b);
        return s;
    }

    // ── State ─────────────────────────────────────────────────────────────────
    var state           = 'idle'; // idle|parsing|importing|paused|done|cancelled
    var queue           = [];
    var currentIndex    = 0;
    var etaTimes        = [];
    var compactExpanded = false;
    var parseDebug      = null; // debug payload returned by parse-zip (shown when edi_debug === '1')
    var lastImportStart = 0;    // timestamp of the last import-deck request start (rate limiting)
    var throttleTimer   = null; // pending setTimeout while waiting to respect the rate limit

    // ── Cached DOM refs (set in renderQueueUI) ────────────────────────────────
    var $form, $container;
    var $progressBar, $progressText, $etaText, $deckTbody, $btnPause, $btnCancel, $summaryWrap, $expandRow;

    // ── Bootstrap ─────────────────────────────────────────────────────────────
    document.addEventListener('DOMContentLoaded', function () {
        $form      = document.querySelector('form[enctype="multipart/form-data"]');
        $container = document.querySelector('.container');
        if (!$form) return;
        $form.addEventListener('submit', onSubmit);
        window.addEventListener('beforeunload', onBeforeUnload);
    });

    function onBeforeUnload(e) {
        if (state !== 'importing' && state !== 'paused') return;
        e.preventDefault();
        e.returnValue = '';
    }

    // ── Form submit intercept ─────────────────────────────────────────────────
    function onSubmit(e) {
        e.preventDefault();
        // Capture FormData BEFORE disabling inputs — disabled controls are excluded from FormData
        var fd = new FormData($form);
        state = 'parsing';
        renderParsingState();

        fetch(SITE_BASE + '/papi/equinox-deck-import/parse-zip', { method: 'POST', body: fd })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (state !== 'parsing') return;
                if (!data.ok) { restoreFormWithError(data.error); return; }

                // No API token → every import would fail with "session expired".
                // Block here with a clear message instead of queuing doomed requests.
                if (data.token_present === false) {
                    restoreFormWithError(t('token_err'));
                    return;
                }

                if (data.decks.length < THRESHOLD) {
                    // Small import: re-enable inputs then fall through to standard PHP POST
                    $form.querySelectorAll('input, select, textarea').forEach(function (el) {
                        el.disabled = false;
                    });
                    $form.removeEventListener('submit', onSubmit);
                    $form.submit();
                    return;
                }

                parseDebug = data.debug || null;
                buildQueue(data.decks);
                renderQueueUI(data.dedup_warn || false);
                state = 'importing';
                importNext();
            })
            .catch(function () {
                if (state !== 'parsing') return;
                restoreFormWithError(t('q_err_network'));
            });
    }

    // ── Parsing state UI ──────────────────────────────────────────────────────
    function renderParsingState() {
        var btn = $form.querySelector('button[type="submit"]');
        if (!btn) return;
        btn._ediOriginalHTML = btn.innerHTML;
        btn.disabled   = true;
        btn.innerHTML  = '<span class="spinner-border spinner-border-sm me-2" role="status"></span>'
                       + escHtml(t('q_parsing'));
        $form.querySelectorAll('input, select, textarea').forEach(function (el) {
            el.disabled = true;
        });
    }

    function restoreFormWithError(msg) {
        state = 'idle';
        var btn = $form.querySelector('button[type="submit"]');
        if (btn) {
            btn.disabled  = false;
            btn.innerHTML = btn._ediOriginalHTML
                || '<i class="fa-solid fa-file-import me-1"></i>' + escHtml(t('submit'));
        }
        $form.querySelectorAll('input, select, textarea').forEach(function (el) {
            el.disabled = false;
        });
        // Remove any previous error
        var prev = $container.querySelector('.edi-parse-error');
        if (prev) prev.remove();
        // Inject error above the form card
        var el = document.createElement('div');
        el.className = 'alert alert-danger py-2 mb-4 edi-parse-error';
        el.innerHTML = '<i class="fa-solid fa-circle-exclamation me-2"></i>' + escHtml(msg);
        var formCard = $form.closest('.card-altered');
        if (formCard) formCard.insertAdjacentElement('beforebegin', el);
    }

    // ── Queue build ───────────────────────────────────────────────────────────
    function buildQueue(decks) {
        queue = decks.map(function (d) {
            return {
                name: d.name, format: d.format, hero: d.hero,
                cards: d.cards, matching_ids: d.matching_ids || [],
                status: 'pending', attempts: 0, error_msg: ''
            };
        });
        currentIndex    = 0;
        etaTimes        = [];
        compactExpanded = false;
        lastImportStart = 0;
        if (throttleTimer !== null) { clearTimeout(throttleTimer); throttleTimer = null; }
    }

    // ── Import loop ───────────────────────────────────────────────────────────
    function importNext() {
        if (state === 'paused' || state === 'cancelled' || state === 'done') return;

        // Advance past fully-processed items
        while (currentIndex < queue.length) {
            var s = queue[currentIndex].status;
            if (s === 'done' || s === 'skip' || s === 'failed_final' || s === 'cancelled') {
                currentIndex++;
            } else {
                break;
            }
        }

        if (currentIndex >= queue.length) { finishQueue(); return; }

        var item = queue[currentIndex];

        // In-flight guard (shouldn't normally be reached)
        if (item.status === 'current') return;

        // Waiting for manual retry
        if (item.status === 'failed') return;

        // Client-side rate limit — keep consecutive import starts at least
        // MIN_INTERVAL apart (RATE_PER_SEC max). If we're too early, schedule the
        // next attempt and bail so the cap is never exceeded.
        var sinceLast = Date.now() - lastImportStart;
        if (sinceLast < MIN_INTERVAL) {
            if (throttleTimer === null) {
                throttleTimer = setTimeout(function () {
                    throttleTimer = null;
                    importNext();
                }, MIN_INTERVAL - sinceLast);
            }
            return;
        }

        // item.status === 'pending'
        lastImportStart = Date.now();
        item.status = 'current';
        var thisStart = lastImportStart;
        updateUI();
        scrollToCurrent();

        fetch(SITE_BASE + '/papi/equinox-deck-import/import-deck', {
            method:  'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                csrf_token:   EDI_CSRF,
                name:         item.name,
                format:       item.format,
                hero:         item.hero,
                cards:        item.cards,
                matching_ids: item.matching_ids
            })
        })
        .then(function (r) { return r.json(); })
        .then(function (data) {
            if (state === 'cancelled') return;
            item.debug = data.debug || null;
            var elapsed = Date.now() - thisStart;
            if (data.ok) {
                item.status = (data.status === 'skip') ? 'skip' : 'done';
                etaTimes.push(elapsed);
                if (etaTimes.length > ETA_WINDOW) etaTimes.shift();
                currentIndex++;
            } else {
                item.attempts++;
                item.error_msg = data.error_msg || t('q_err_generic');
                if (item.attempts >= MAX_RETRIES) {
                    item.status = 'failed_final';
                    currentIndex++;
                } else {
                    item.status = 'failed';
                }
            }
            updateUI();
            if (item.status !== 'failed') importNext();
        })
        .catch(function () {
            if (state === 'cancelled') return;
            item.attempts++;
            item.error_msg = t('q_err_network');
            if (item.attempts >= MAX_RETRIES) {
                item.status = 'failed_final';
                currentIndex++;
                updateUI();
                importNext();
            } else {
                item.status = 'failed';
                updateUI();
            }
        });
    }

    function retryItem(index) {
        var item = queue[index];
        if (!item || item.status !== 'failed') return;
        if (state === 'done' || state === 'cancelled') return;
        item.status  = 'pending';
        currentIndex = Math.min(currentIndex, index);
        updateUI();
        if (state === 'importing') importNext();
    }

    function pauseQueue() {
        state = 'paused';
        updateUI();
    }

    function resumeQueue() {
        state = 'importing';
        // importNext() handles positioning — if the current slot is a failed
        // item, the loop stops there again and the user must click Retry first.
        importNext();
    }

    function cancelQueue() {
        state = 'cancelled';
        if (throttleTimer !== null) { clearTimeout(throttleTimer); throttleTimer = null; }
        queue.forEach(function (item) {
            if (item.status === 'pending' || item.status === 'current') {
                item.status = 'cancelled';
            }
        });
        finishQueue();
    }

    function finishQueue() {
        if (state !== 'cancelled') state = 'done';
        window.removeEventListener('beforeunload', onBeforeUnload);
        updateUI();
    }

    // ── ETA ───────────────────────────────────────────────────────────────────
    function getEta() {
        if (etaTimes.length === 0) return '';
        var avg    = etaTimes.reduce(function (a, b) { return a + b; }, 0) / etaTimes.length;
        // Account for the rate limit: each deck takes at least MIN_INTERVAL of
        // wall-clock even when the request itself is faster than that.
        var perDeck = Math.max(avg, MIN_INTERVAL);
        var remain = queue.filter(function (i) {
            return i.status === 'pending';
        }).length;
        var ms = perDeck * remain;
        if (ms < 2000) return '';
        var secs = Math.round(ms / 1000);
        if (secs < 60) return tf('q_eta_sec', secs);
        var mins = Math.floor(secs / 60);
        var s2   = secs % 60;
        return s2 > 0 ? tf('q_eta_min_sec', mins, s2) : tf('q_eta_min', mins);
    }

    // ── Counts ────────────────────────────────────────────────────────────────
    function getCounts() {
        var done = 0, skip = 0, failed = 0, pending = 0, cancelled = 0;
        queue.forEach(function (i) {
            if      (i.status === 'done')                                  done++;
            else if (i.status === 'skip')                                  skip++;
            else if (i.status === 'failed' || i.status === 'failed_final') failed++;
            else if (i.status === 'pending' || i.status === 'current')     pending++;
            else if (i.status === 'cancelled')                             cancelled++;
        });
        return { done: done, skip: skip, failed: failed, pending: pending, cancelled: cancelled };
    }

    function processedCount() {
        return queue.filter(function (i) {
            return i.status === 'done' || i.status === 'skip' ||
                   i.status === 'failed_final' || i.status === 'cancelled';
        }).length;
    }

    // ── Queue UI rendering ────────────────────────────────────────────────────
    function renderQueueUI(dedupWarn) {
        // Hide upload form
        var formCard = $form.closest('.card-altered');
        if (formCard) formCard.style.display = 'none';

        // Remove previous queue UI if any
        var old = document.getElementById('edi-queue-ui');
        if (old) old.remove();

        var wrap = document.createElement('div');
        wrap.id  = 'edi-queue-ui';

        // Dedup warning
        if (dedupWarn) {
            var warn = document.createElement('div');
            warn.className = 'alert alert-warning py-2 mb-3 small';
            warn.innerHTML = '<i class="fa-solid fa-triangle-exclamation me-2"></i>'
                           + escHtml(t('q_dedup_warn'));
            wrap.appendChild(warn);
        }

        // Parse / dedup debug panel — only when localStorage 'edi_debug' === '1'
        if (debugOn() && parseDebug) {
            var dbgEl = document.createElement('div');
            dbgEl.className = 'edi-debug mb-3';
            dbgEl.innerHTML = parseDebugHTML(parseDebug);
            wrap.appendChild(dbgEl);
        }

        // Progress header card
        var header = document.createElement('div');
        header.id        = 'edi-progress-header';
        header.className = 'card-altered p-3 mb-3';
        header.innerHTML =
            '<div class="d-flex justify-content-between align-items-center mb-2">'
          +   '<span id="edi-progress-text" class="fw-semibold"></span>'
          +   '<span id="edi-eta-text" class="small text-muted"></span>'
          + '</div>'
          + '<div class="progress mb-3" style="height:8px" role="progressbar" '
          +      'aria-valuemin="0" aria-valuemax="100" aria-valuenow="0">'
          +   '<div id="edi-progress-bar" class="progress-bar progress-bar-striped '
          +        'progress-bar-animated" style="width:0%"></div>'
          + '</div>'
          + '<div class="d-flex gap-2">'
          +   '<button id="edi-btn-pause" type="button" class="btn btn-sm btn-outline-secondary">'
          +     '<i class="fa-solid fa-pause me-1"></i>' + escHtml(t('q_pause'))
          +   '</button>'
          +   '<button id="edi-btn-cancel" type="button" class="btn btn-sm btn-outline-danger">'
          +     '<i class="fa-solid fa-xmark me-1"></i>' + escHtml(t('q_cancel'))
          +   '</button>'
          + '</div>';
        wrap.appendChild(header);

        // Summary (hidden until done)
        var summary = document.createElement('div');
        summary.id           = 'edi-summary';
        summary.style.display = 'none';
        wrap.appendChild(summary);

        // Deck table
        var tableWrap = document.createElement('div');
        tableWrap.className = 'card-altered mb-4';
        tableWrap.innerHTML =
            '<table class="table table-sm table-altered mb-0">'
          +   '<thead><tr>'
          +     '<th style="width:70%">' + escHtml(t('col_deck')) + '</th>'
          +     '<th>' + escHtml(t('col_status')) + '</th>'
          +   '</tr></thead>'
          +   '<tbody id="edi-deck-tbody"></tbody>'
          + '</table>';
        wrap.appendChild(tableWrap);

        // "Import another" button (hidden until done)
        var resetBtn = document.createElement('button');
        resetBtn.id            = 'edi-btn-reset';
        resetBtn.type          = 'button';
        resetBtn.className     = 'btn btn-primary-altered btn-sm mb-4';
        resetBtn.style.display = 'none';
        resetBtn.innerHTML     = '<i class="fa-solid fa-plus me-1"></i>' + escHtml(t('q_reset'));
        resetBtn.addEventListener('click', function () { window.location.reload(); });
        wrap.appendChild(resetBtn);

        $container.appendChild(wrap);

        // Cache refs
        $progressBar  = document.getElementById('edi-progress-bar');
        $progressText = document.getElementById('edi-progress-text');
        $etaText      = document.getElementById('edi-eta-text');
        $deckTbody    = document.getElementById('edi-deck-tbody');
        $summaryWrap  = document.getElementById('edi-summary');
        $btnPause     = document.getElementById('edi-btn-pause');
        $btnCancel    = document.getElementById('edi-btn-cancel');

        $btnPause.addEventListener('click', function () {
            if (state === 'importing') pauseQueue();
            else if (state === 'paused') resumeQueue();
        });
        $btnCancel.addEventListener('click', function () {
            if (window.confirm(t('q_cancel_confirm'))) {
                cancelQueue();
            }
        });

        // Populate rows
        queue.forEach(function (_, idx) {
            var tr = document.createElement('tr');
            tr.id  = 'edi-row-' + idx;
            $deckTbody.appendChild(tr);
        });

        // Compact mode expand row — appended AFTER deck rows so $expandRow can be assigned directly
        var expandTr = document.createElement('tr');
        expandTr.id  = 'edi-compact-expand';
        expandTr.style.display = 'none';
        expandTr.innerHTML = '<td colspan="2" class="text-center py-2">'
            + '<button type="button" class="btn btn-link btn-sm p-0 edi-expand-btn"></button>'
            + '</td>';
        expandTr.querySelector('.edi-expand-btn').addEventListener('click', function () {
            compactExpanded = true;
            applyCompactMode();
        });
        $deckTbody.appendChild(expandTr);
        $expandRow = expandTr;

        // Event delegation for retry buttons (attached once, survives row redraws)
        $deckTbody.addEventListener('click', function (e) {
            var btn = e.target.closest('.edi-retry-btn');
            if (!btn) return;
            retryItem(parseInt(btn.getAttribute('data-idx'), 10));
        });

        updateUI();
    }

    // ── UI update (called after every state change) ───────────────────────────
    function updateUI() {
        if (!$progressBar) return;

        var total = queue.length;
        var proc  = processedCount();
        var pct   = total > 0 ? Math.round((proc / total) * 100) : 0;

        // Progress bar
        $progressBar.style.width = pct + '%';
        $progressBar.closest('[role="progressbar"]').setAttribute('aria-valuenow', pct);
        if (state === 'done' || state === 'cancelled') {
            $progressBar.classList.remove('progress-bar-animated', 'progress-bar-striped');
        }

        // Header text
        if (state === 'done' || state === 'cancelled') {
            $progressText.textContent = state === 'done' ? t('q_done') : t('q_cancelled');
        } else {
            $progressText.textContent = tf('q_progress', proc, total);
        }

        // ETA
        $etaText.textContent = (state === 'importing') ? getEta() : '';

        // Pause / Cancel buttons
        var finished = (state === 'done' || state === 'cancelled');
        if ($btnPause) {
            $btnPause.style.display = finished ? 'none' : '';
            $btnPause.innerHTML = state === 'paused'
                ? '<i class="fa-solid fa-play me-1"></i>' + escHtml(t('q_resume'))
                : '<i class="fa-solid fa-pause me-1"></i>' + escHtml(t('q_pause'));
        }
        if ($btnCancel) $btnCancel.style.display = finished ? 'none' : '';

        // "Import another" button
        var resetBtn = document.getElementById('edi-btn-reset');
        if (resetBtn) resetBtn.style.display = finished ? '' : 'none';

        // Redraw all rows
        queue.forEach(function (item, idx) {
            var tr = document.getElementById('edi-row-' + idx);
            if (tr) tr.innerHTML = rowHTML(item, idx);
        });

        // Compact mode for large queues
        applyCompactMode();

        // Summary on finish
        if (finished) renderSummary();
    }

    function rowHTML(item, idx) {
        var badge  = statusBadge(item);
        var detail = '';
        if (item.status === 'failed') {
            var left = MAX_RETRIES - item.attempts;
            detail = '<div class="mt-1 small text-danger">'
                   + escHtml(item.error_msg)
                   + ' <button type="button" class="btn btn-link btn-sm p-0 edi-retry-btn" data-idx="' + idx + '">'
                   + '<i class="fa-solid fa-rotate-left me-1"></i>' + escHtml(t('q_retry'))
                   + ' (' + escHtml(tf(left === 1 ? 'q_retry_left_1' : 'q_retry_left_n', left)) + ')'
                   + '</button></div>';
        } else if (item.status === 'failed_final') {
            detail = '<div class="mt-1 small text-danger">' + escHtml(item.error_msg) + '</div>';
        }
        if (debugOn() && item.debug) {
            detail += deckDebugHTML(item.debug);
        }
        return '<td>' + escHtml(item.name) + detail + '</td><td>' + badge + '</td>';
    }

    function statusBadge(item) {
        switch (item.status) {
            case 'done':
                return '<span class="badge bg-success">' + escHtml(t('deck_ok')) + '</span>';
            case 'skip':
                return '<span class="badge bg-secondary">' + escHtml(t('deck_skip')) + '</span>';
            case 'current':
                return '<span class="badge bg-primary">'
                     + '<span class="spinner-border spinner-border-sm me-1" '
                     + 'style="width:.65rem;height:.65rem" role="status"></span>'
                     + escHtml(t('q_current')) + '</span>';
            case 'failed':
                return '<span class="badge bg-warning text-dark">' + escHtml(t('deck_err')) + '</span>';
            case 'failed_final':
                return '<span class="badge bg-danger">' + escHtml(t('q_failed_final')) + '</span>';
            case 'cancelled':
                return '<span class="badge bg-secondary">' + escHtml(t('q_cancelled_item')) + '</span>';
            default:
                return '<span class="badge badge-pending">' + escHtml(t('q_pending')) + '</span>';
        }
    }

    function applyCompactMode() {
        if (queue.length <= 100) return;

        if (compactExpanded) {
            queue.forEach(function (_, idx) {
                var tr = document.getElementById('edi-row-' + idx);
                if (tr) tr.style.display = '';
            });
            if ($expandRow) $expandRow.style.display = 'none';
            return;
        }

        var SHOW_RECENT = 3;
        var doneIdxs    = [];
        queue.forEach(function (item, idx) {
            if (item.status === 'done' || item.status === 'skip') doneIdxs.push(idx);
        });
        var recentSet = {};
        doneIdxs.slice(-SHOW_RECENT).forEach(function (i) { recentSet[i] = true; });

        var hiddenCount = 0;
        queue.forEach(function (item, idx) {
            var tr = document.getElementById('edi-row-' + idx);
            if (!tr) return;
            var collapsible = (item.status === 'done' || item.status === 'skip') && !recentSet[idx];
            tr.style.display = collapsible ? 'none' : '';
            if (collapsible) hiddenCount++;
        });

        if ($expandRow) {
            if (hiddenCount > 0) {
                var btn = $expandRow.querySelector('.edi-expand-btn');
                if (btn) btn.textContent = tf('q_show_all', queue.length);
                $expandRow.style.display = '';
            } else {
                $expandRow.style.display = 'none';
            }
        }
    }

    function renderSummary() {
        if (!$summaryWrap) return;
        var c           = getCounts();
        var isCancelled = (state === 'cancelled');
        var type        = c.failed > 0
            ? (c.done > 0 || c.skip > 0 ? 'warning' : 'danger')
            : (isCancelled ? 'warning' : 'success');
        var icon        = (c.failed > 0 || isCancelled) ? 'triangle-exclamation' : 'check';
        var parts       = [];
        if (c.done      > 0) parts.push(tf(c.done      === 1 ? 'q_sum_imported_1'  : 'q_sum_imported_n',  c.done));
        if (c.skip      > 0) parts.push(tf(c.skip      === 1 ? 'q_sum_skip_1'      : 'q_sum_skip_n',      c.skip));
        if (c.failed    > 0) parts.push(tf(c.failed    === 1 ? 'q_sum_failed_1'    : 'q_sum_failed_n',    c.failed));
        if (c.cancelled > 0) parts.push(tf(c.cancelled === 1 ? 'q_sum_cancelled_1' : 'q_sum_cancelled_n', c.cancelled));
        $summaryWrap.className     = 'alert alert-' + type + ' py-2 mb-3';
        $summaryWrap.innerHTML     = '<i class="fa-solid fa-' + icon + ' me-2"></i>' + escHtml(parts.join(' · '));
        $summaryWrap.style.display = '';
    }

    function scrollToCurrent() {
        var tr = document.getElementById('edi-row-' + currentIndex);
        if (tr) tr.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    }

    // ── Utility ───────────────────────────────────────────────────────────────
    function escHtml(s) {
        return String(s)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    // ── Debug (mirrors the server-side debug; enable with localStorage.setItem('edi_debug','1') in the console) ──
    function debugOn() {
        try { return localStorage.getItem('edi_debug') === '1'; } catch (e) { return false; }
    }

    function kv(label, valueHtml) {
        return '<div><span class="text-muted">' + escHtml(label) + ':</span> ' + valueHtml + '</div>';
    }

    // Global panel built from the parse-zip response (name matching, near-misses, fetch status).
    function parseDebugHTML(pd) {
        var html = '<div class="card-altered p-3 small font-monospace overflow-auto">';
        html += '<div class="text-muted mb-2"><i class="fa-solid fa-magnifying-glass me-1"></i>Parse / dedup debug</div>';
        html += kv('decks parsed', escHtml(String(pd.parsed_deck_count != null ? pd.parsed_deck_count : '?')));
        html += kv('token present', pd.token_present ? 'yes' : '<span class="text-danger">no</span>');
        html += kv('dedup warn', pd.dedup_warn
            ? '<span class="text-warning-emphasis">yes</span> (' + escHtml(pd.dedup_warn_reason || '') + ')'
            : 'no');
        if (pd.fetch) {
            html += kv('fetch http', escHtml(String(pd.fetch.http != null ? pd.fetch.http : '?')));
            if (pd.fetch.url)        html += kv('fetch url', escHtml(pd.fetch.url));
            if (pd.fetch.curl_error) html += kv('curl error', '<span class="text-danger">' + escHtml(pd.fetch.curl_error) + '</span>');
            if (pd.fetch.response_preview) {
                html += '<div class="text-muted mt-1">response:</div>'
                      + '<pre class="bg-light p-2 rounded mb-1" style="max-height:200px;white-space:pre-wrap;word-break:break-all">'
                      + escHtml(pd.fetch.response_preview) + '</pre>';
            }
        }
        html += kv('API deck count', escHtml(String(pd.api_deck_count != null ? pd.api_deck_count : 0)));

        var nm = pd.near_misses || [];
        if (nm.length) {
            html += '<div class="alert alert-warning p-2 rounded my-2"><strong>Near-misses</strong> (same name, bytes differ):';
            nm.forEach(function (m) {
                html += '<div class="mt-1">CSV: "' + escHtml(m.incoming) + '" <span class="opacity-50">' + escHtml(m.incoming_hex) + '</span></div>';
                html += '<div>API: "' + escHtml(m.api) + '" <span class="opacity-50">' + escHtml(m.api_hex) + '</span></div>';
            });
            html += '</div>';
        }

        var inc = pd.incoming_names || [];
        var api = pd.api_names || [];
        html += '<div class="row g-3 mt-1">';
        html += '<div class="col"><div class="text-muted mb-1">Incoming (CSV)</div>';
        inc.forEach(function (n) {
            html += '<div class="py-1">"' + escHtml(n.value) + '" <span class="text-muted opacity-50">' + escHtml(n.hex) + '</span></div>';
        });
        html += '</div>';
        html += '<div class="col"><div class="text-muted mb-1">API deck names</div>';
        api.forEach(function (n) {
            var strict = inc.some(function (i) { return i.value === n.value; });
            html += '<div class="py-1 ' + (strict ? 'text-success' : '') + '">"' + escHtml(n.value) + '" '
                  + '<span class="text-muted opacity-50">' + escHtml(n.hex) + '</span>' + (strict ? ' &#10003;' : '') + '</div>';
        });
        html += '</div></div>';
        html += '</div>';
        return html;
    }

    // Per-deck collapsible panel built from the import-deck response.
    function deckDebugHTML(dbg) {
        var html = '<details class="edi-debug mt-1"><summary class="small text-muted" style="cursor:pointer">'
                 + '<i class="fa-solid fa-bug me-1"></i>debug</summary>';
        html += '<div class="small font-monospace mt-1 p-2 rounded" style="background:rgba(0,0,0,.04)">';
        if (dbg.request) {
            var r = dbg.request;
            html += kv('cards', escHtml(String(r.card_count)) + ' (normalized ' + escHtml(String(r.normalized_count)) + ')');
            html += kv('format', escHtml(r.format || ''));
            if (r.hero) html += kv('hero', escHtml(r.hero));
            html += kv('incoming hash', '<span class="text-primary">' + escHtml(r.incoming_hash || '') + '</span>');
        }
        if (dbg.dedup) {
            var d = dbg.dedup;
            html += kv('dedup decision', d.decision === 'skip'
                ? '<span class="text-secondary">skip (duplicate)</span>'
                : 'import');
            var ids = d.matching_ids || [];
            if (ids.length) html += kv('matching ids', escHtml(ids.join(', ')));
            (d.checked || []).forEach(function (c) {
                html += '<div class="' + (c.matched ? 'text-success' : '') + '">&mdash; "' + escHtml(c.name || c.id) + '"'
                      + ' &middot; cards ' + escHtml(String(c.cards_count))
                      + ' &middot; hash ' + escHtml(c.api_hash) + (c.matched ? ' &#10003; match' : '') + '</div>';
            });
            var fe = d.fetch_errors || {};
            Object.keys(fe).forEach(function (id) {
                html += '<div class="text-danger">fetch error ' + escHtml(id) + ': http ' + escHtml(String(fe[id].http || 0)) + '</div>';
            });
        }
        if (dbg.import) {
            var im = dbg.import;
            html += kv('import http', escHtml(String(im.http)));
            if (im.error) html += kv('error', '<span class="text-danger">' + escHtml(im.error) + '</span>');
            if (im.response_preview) {
                html += '<div class="text-muted mt-1">API response:</div>'
                      + '<pre class="mb-0" style="max-height:200px;white-space:pre-wrap;word-break:break-all">'
                      + escHtml(im.response_preview) + '</pre>';
            }
        }
        html += '</div></details>';
        return html;
    }

}());
