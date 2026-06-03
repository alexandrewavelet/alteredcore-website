<?php
// Test bootstrap: load the plugin autoloader (domain classes are pure — no host
// functions, no DB — so they resolve standalone) and the assertion harness.
require_once __DIR__ . '/../inc/bootstrap.php';
require_once __DIR__ . '/assert.php';
