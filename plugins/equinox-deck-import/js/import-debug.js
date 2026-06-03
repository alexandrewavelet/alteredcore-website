// Equinox Deck Import — debug panel rendering (EDI.Debug).
// Builds the diagnostic HTML shown when localStorage edi_debug === '1':
// a global parse/dedup panel and a per-deck collapsible panel.
(function () {
    'use strict';

    var EDI = window.EDI = window.EDI || {};
    var esc = EDI.util.escHtml;

    function kv(label, valueHtml) {
        return '<div><span class="text-muted">' + esc(label) + ':</span> ' + valueHtml + '</div>';
    }

    EDI.Debug = {
        // Global panel built from the parse-zip response (name matching, near-misses, fetch status).
        parsePanelHTML: function (pd) {
            var html = '<div class="card-altered p-3 small font-monospace overflow-auto">';
            html += '<div class="text-muted mb-2"><i class="fa-solid fa-magnifying-glass me-1"></i>Parse / dedup debug</div>';
            html += kv('decks parsed', esc(String(pd.parsed_deck_count != null ? pd.parsed_deck_count : '?')));
            html += kv('token present', pd.token_present ? 'yes' : '<span class="text-danger">no</span>');
            html += kv('dedup warn', pd.dedup_warn
                ? '<span class="text-warning-emphasis">yes</span> (' + esc(pd.dedup_warn_reason || '') + ')'
                : 'no');
            if (pd.fetch) {
                html += kv('fetch http', esc(String(pd.fetch.http != null ? pd.fetch.http : '?')));
                if (pd.fetch.url) { html += kv('fetch url', esc(pd.fetch.url)); }
                if (pd.fetch.curl_error) { html += kv('curl error', '<span class="text-danger">' + esc(pd.fetch.curl_error) + '</span>'); }
                if (pd.fetch.response_preview) {
                    html += '<div class="text-muted mt-1">response:</div>'
                          + '<pre class="bg-light p-2 rounded mb-1" style="max-height:200px;white-space:pre-wrap;word-break:break-all">'
                          + esc(pd.fetch.response_preview) + '</pre>';
                }
            }
            html += kv('API deck count', esc(String(pd.api_deck_count != null ? pd.api_deck_count : 0)));

            var nm = pd.near_misses || [];
            if (nm.length) {
                html += '<div class="alert alert-warning p-2 rounded my-2"><strong>Near-misses</strong> (same name, bytes differ):';
                nm.forEach(function (m) {
                    html += '<div class="mt-1">CSV: "' + esc(m.incoming) + '" <span class="opacity-50">' + esc(m.incoming_hex) + '</span></div>';
                    html += '<div>API: "' + esc(m.api) + '" <span class="opacity-50">' + esc(m.api_hex) + '</span></div>';
                });
                html += '</div>';
            }

            var inc = pd.incoming_names || [];
            var api = pd.api_names || [];
            html += '<div class="row g-3 mt-1">';
            html += '<div class="col"><div class="text-muted mb-1">Incoming (CSV)</div>';
            inc.forEach(function (n) {
                html += '<div class="py-1">"' + esc(n.value) + '" <span class="text-muted opacity-50">' + esc(n.hex) + '</span></div>';
            });
            html += '</div>';
            html += '<div class="col"><div class="text-muted mb-1">API deck names</div>';
            api.forEach(function (n) {
                var strict = inc.some(function (i) { return i.value === n.value; });
                html += '<div class="py-1 ' + (strict ? 'text-success' : '') + '">"' + esc(n.value) + '" '
                      + '<span class="text-muted opacity-50">' + esc(n.hex) + '</span>' + (strict ? ' &#10003;' : '') + '</div>';
            });
            html += '</div></div>';
            html += '</div>';
            return html;
        },

        // Per-deck collapsible panel built from the import-deck response.
        deckPanelHTML: function (dbg) {
            var html = '<details class="edi-debug mt-1"><summary class="small text-muted" style="cursor:pointer">'
                     + '<i class="fa-solid fa-bug me-1"></i>debug</summary>';
            html += '<div class="small font-monospace mt-1 p-2 rounded" style="background:rgba(0,0,0,.04)">';
            if (dbg.request) {
                var r = dbg.request;
                html += kv('cards', esc(String(r.card_count)) + ' (normalized ' + esc(String(r.normalized_count)) + ')');
                html += kv('format', esc(r.format || ''));
                if (r.hero) { html += kv('hero', esc(r.hero)); }
                html += kv('incoming hash', '<span class="text-primary">' + esc(r.incoming_hash || '') + '</span>');
            }
            if (dbg.dedup) {
                var d = dbg.dedup;
                html += kv('dedup decision', d.decision === 'skip'
                    ? '<span class="text-secondary">skip (duplicate)</span>'
                    : 'import');
                var ids = d.matching_ids || [];
                if (ids.length) { html += kv('matching ids', esc(ids.join(', '))); }
                (d.checked || []).forEach(function (c) {
                    html += '<div class="' + (c.matched ? 'text-success' : '') + '">&mdash; "' + esc(c.name || c.id) + '"'
                          + ' &middot; cards ' + esc(String(c.cards_count))
                          + ' &middot; hash ' + esc(c.api_hash) + (c.matched ? ' &#10003; match' : '') + '</div>';
                });
                var fe = d.fetch_errors || {};
                Object.keys(fe).forEach(function (id) {
                    html += '<div class="text-danger">fetch error ' + esc(id) + ': http ' + esc(String(fe[id].http || 0)) + '</div>';
                });
            }
            if (dbg.import) {
                var im = dbg.import;
                html += kv('import http', esc(String(im.http)));
                if (im.error) { html += kv('error', '<span class="text-danger">' + esc(im.error) + '</span>'); }
                if (im.response_preview) {
                    html += '<div class="text-muted mt-1">API response:</div>'
                          + '<pre class="mb-0" style="max-height:200px;white-space:pre-wrap;word-break:break-all">'
                          + esc(im.response_preview) + '</pre>';
                }
            }
            html += '</div></details>';
            return html;
        }
    };
})();
