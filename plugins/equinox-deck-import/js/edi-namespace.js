// Equinox Deck Import — shared namespace + small utilities.
// Loaded first (see plugin.json assets.js order). All modules hang off window.EDI.
(function () {
    'use strict';

    var EDI = window.EDI = window.EDI || {};

    EDI.util = {
        // HTML-escape for safe innerHTML injection.
        escHtml: function (s) {
            return String(s)
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#039;');
        },

        // Translate a key from the injected EDI_TXT table (falls back to the key).
        t: function (key) {
            var T = (typeof window.EDI_TXT !== 'undefined') ? window.EDI_TXT : {};
            return (T[key] !== undefined) ? T[key] : key;
        },

        // Translate + substitute %1 / %2.
        tf: function (key, a, b) {
            var s = EDI.util.t(key);
            if (a !== undefined) { s = s.replace('%1', a); }
            if (b !== undefined) { s = s.replace('%2', b); }
            return s;
        },

        // Debug panels are opt-in: set localStorage edi_debug = '1' in the console.
        debugOn: function () {
            try { return localStorage.getItem('edi_debug') === '1'; }
            catch (e) { return false; }
        }
    };
})();
