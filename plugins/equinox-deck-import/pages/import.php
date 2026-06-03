<?php
// Front-end page: the Equinox import UI. Presentation only — all importing is
// driven client-side by the queue (js/) hitting the papi/ endpoints.
require_once __DIR__ . '/../inc/bootstrap.php';

use AlteredCore\EquinoxDeckImport\Presentation\ImportView;

if (function_exists('loginRequired')) {
    loginRequired();
}

(new ImportView())->render();
