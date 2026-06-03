// Equinox Deck Import — wiring. Intercepts the upload form, parses the ZIP via
// the parse-zip endpoint, then drives the import queue (all imports, any size).
(function () {
    'use strict';

    var EDI = window.EDI;

    document.addEventListener('DOMContentLoaded', function () {
        var form      = document.querySelector('form[enctype="multipart/form-data"]');
        var container = document.querySelector('.container');
        if (!form || !container) { return; }

        var siteBase = (typeof window.SITE_BASE !== 'undefined') ? window.SITE_BASE : '';
        var csrf     = (typeof window.EDI_CSRF  !== 'undefined') ? window.EDI_CSRF  : '';

        var queue   = null;
        var parsing = false;

        var view = EDI.View.create({
            form: form,
            container: container,
            handlers: {
                onTogglePause: function () { if (queue) { queue.togglePause(); } },
                onCancel:      function () { if (queue) { queue.cancel(); } },
                onRetry:       function (idx) { if (queue) { queue.retry(idx); } },
                onReset:       function () { window.location.reload(); }
            }
        });

        form.addEventListener('submit', function (e) {
            e.preventDefault();
            if (parsing || queue) { return; } // ignore re-submits during parse / once running
            parsing = true;

            // Capture FormData BEFORE disabling inputs (disabled controls are excluded).
            var fd = new FormData(form);
            fd.append('debug', EDI.util.debugOn() ? '1' : '0');
            view.showParsing();

            fetch(siteBase + '/papi/equinox-deck-import/parse-zip', { method: 'POST', body: fd })
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    if (!data.ok) {
                        parsing = false;
                        view.showError(data.error || EDI.util.t('q_err_generic'));
                        return;
                    }
                    // No API token → every import would fail; block with a clear message.
                    if (data.token_present === false) {
                        parsing = false;
                        view.showError(EDI.util.t('token_err'));
                        return;
                    }
                    queue = EDI.Queue.create({
                        siteBase: siteBase,
                        csrf:     csrf,
                        onChange: function (snapshot) { view.update(snapshot); }
                    });
                    queue.start(data.decks, data.dedup_warn || false, data.debug || null);
                })
                .catch(function () {
                    parsing = false;
                    view.showError(EDI.util.t('q_err_network'));
                });
        });
    });
})();
