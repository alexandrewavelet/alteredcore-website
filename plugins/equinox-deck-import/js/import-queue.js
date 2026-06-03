// Equinox Deck Import — import queue engine (EDI.Queue).
// Owns the import state machine, the client-side rate limit, retries and ETA.
// View-agnostic: after every state change it calls opts.onChange(snapshot()).
(function () {
    'use strict';

    var EDI = window.EDI = window.EDI || {};
    var t   = EDI.util.t;

    var MAX_RETRIES  = 3;
    var ETA_WINDOW   = 5;
    var RATE_PER_SEC = 3;                              // client-side cap: max import starts per second
    var MIN_INTERVAL = Math.ceil(1000 / RATE_PER_SEC); // min ms between two consecutive import starts (~334ms)

    function create(opts) {
        var siteBase = opts.siteBase || '';
        var csrf     = opts.csrf || '';
        var onChange = opts.onChange || function () {};

        var state           = 'idle'; // idle|importing|paused|done|cancelled
        var queue           = [];
        var currentIndex    = 0;
        var etaTimes        = [];
        var lastImportStart = 0;
        var throttleTimer   = null;
        var dedupWarn       = false;
        var parseDebug      = null;

        function onBeforeUnload(e) {
            if (state !== 'importing' && state !== 'paused') { return; }
            e.preventDefault();
            e.returnValue = '';
        }

        function emit() { onChange(snapshot()); }

        function start(decks, warn, pdebug) {
            dedupWarn  = !!warn;
            parseDebug = pdebug || null;
            queue = decks.map(function (d) {
                return {
                    name: d.name, format: d.format, hero: d.hero,
                    cards: d.cards, matching_ids: d.matching_ids || [],
                    status: 'pending', attempts: 0, error_msg: '', debug: null
                };
            });
            currentIndex    = 0;
            etaTimes        = [];
            lastImportStart = 0;
            if (throttleTimer !== null) { clearTimeout(throttleTimer); throttleTimer = null; }
            state = 'importing';
            window.addEventListener('beforeunload', onBeforeUnload);
            emit();
            importNext();
        }

        function importNext() {
            if (state === 'paused' || state === 'cancelled' || state === 'done') { return; }

            // Advance past fully-processed items.
            while (currentIndex < queue.length) {
                var s = queue[currentIndex].status;
                if (s === 'done' || s === 'skip' || s === 'failed_final' || s === 'cancelled') { currentIndex++; }
                else { break; }
            }
            if (currentIndex >= queue.length) { finish(); return; }

            var item = queue[currentIndex];
            if (item.status === 'current') { return; } // in-flight
            if (item.status === 'failed')  { return; } // waiting for manual retry

            // Client-side rate limit — keep starts at least MIN_INTERVAL apart.
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

            lastImportStart = Date.now();
            item.status = 'current';
            var thisStart = lastImportStart;
            emit();

            fetch(siteBase + '/papi/equinox-deck-import/import-deck', {
                method:  'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    csrf_token:   csrf,
                    debug:        EDI.util.debugOn() ? 1 : 0,
                    name:         item.name,
                    format:       item.format,
                    hero:         item.hero,
                    cards:        item.cards,
                    matching_ids: item.matching_ids
                })
            })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (state === 'cancelled') { return; }
                item.debug = data.debug || null;
                var elapsed = Date.now() - thisStart;
                if (data.ok) {
                    item.status = (data.status === 'skip') ? 'skip' : 'done';
                    etaTimes.push(elapsed);
                    if (etaTimes.length > ETA_WINDOW) { etaTimes.shift(); }
                    currentIndex++;
                } else {
                    item.attempts++;
                    item.error_msg = data.error_msg || t('q_err_generic');
                    if (item.attempts >= MAX_RETRIES) { item.status = 'failed_final'; currentIndex++; }
                    else { item.status = 'failed'; }
                }
                emit();
                if (item.status !== 'failed') { importNext(); }
            })
            .catch(function () {
                if (state === 'cancelled') { return; }
                item.attempts++;
                item.error_msg = t('q_err_network');
                if (item.attempts >= MAX_RETRIES) {
                    item.status = 'failed_final';
                    currentIndex++;
                    emit();
                    importNext();
                } else {
                    item.status = 'failed';
                    emit();
                }
            });
        }

        function retry(index) {
            var item = queue[index];
            if (!item || item.status !== 'failed') { return; }
            if (state === 'done' || state === 'cancelled') { return; }
            item.status  = 'pending';
            currentIndex = Math.min(currentIndex, index);
            emit();
            if (state === 'importing') { importNext(); }
        }

        function togglePause() {
            if (state === 'importing') { state = 'paused'; emit(); }
            else if (state === 'paused') { state = 'importing'; emit(); importNext(); }
        }

        function cancel() {
            state = 'cancelled';
            if (throttleTimer !== null) { clearTimeout(throttleTimer); throttleTimer = null; }
            queue.forEach(function (item) {
                if (item.status === 'pending' || item.status === 'current') { item.status = 'cancelled'; }
            });
            finish();
        }

        function finish() {
            if (state !== 'cancelled') { state = 'done'; }
            window.removeEventListener('beforeunload', onBeforeUnload);
            emit();
        }

        function processedCount() {
            return queue.filter(function (i) {
                return i.status === 'done' || i.status === 'skip' ||
                       i.status === 'failed_final' || i.status === 'cancelled';
            }).length;
        }

        function counts() {
            var c = { done: 0, skip: 0, failed: 0, pending: 0, cancelled: 0 };
            queue.forEach(function (i) {
                if      (i.status === 'done')                                  { c.done++; }
                else if (i.status === 'skip')                                  { c.skip++; }
                else if (i.status === 'failed' || i.status === 'failed_final') { c.failed++; }
                else if (i.status === 'pending' || i.status === 'current')     { c.pending++; }
                else if (i.status === 'cancelled')                             { c.cancelled++; }
            });
            return c;
        }

        function eta() {
            if (etaTimes.length === 0) { return ''; }
            var avg = etaTimes.reduce(function (a, b) { return a + b; }, 0) / etaTimes.length;
            // Each deck takes at least MIN_INTERVAL of wall-clock even when faster.
            var perDeck = Math.max(avg, MIN_INTERVAL);
            var remain  = queue.filter(function (i) { return i.status === 'pending'; }).length;
            var ms = perDeck * remain;
            if (ms < 2000) { return ''; }
            var secs = Math.round(ms / 1000);
            if (secs < 60) { return EDI.util.tf('q_eta_sec', secs); }
            var mins = Math.floor(secs / 60);
            var s2   = secs % 60;
            return s2 > 0 ? EDI.util.tf('q_eta_min_sec', mins, s2) : EDI.util.tf('q_eta_min', mins);
        }

        function snapshot() {
            var total = queue.length;
            var proc  = processedCount();
            return {
                state:        state,
                items:        queue,
                total:        total,
                processed:    proc,
                pct:          total > 0 ? Math.round((proc / total) * 100) : 0,
                counts:       counts(),
                eta:          (state === 'importing') ? eta() : '',
                dedupWarn:    dedupWarn,
                parseDebug:   parseDebug,
                finished:     (state === 'done' || state === 'cancelled'),
                maxRetries:   MAX_RETRIES,
                currentIndex: currentIndex
            };
        }

        return {
            start:       start,
            togglePause: togglePause,
            cancel:      cancel,
            retry:       retry
        };
    }

    EDI.Queue = { create: create };
})();
