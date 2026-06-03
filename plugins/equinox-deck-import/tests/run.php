<?php
// Plain-PHP test runner (no phpunit). Usage: php tests/run.php
require __DIR__ . '/bootstrap.php';

foreach (glob(__DIR__ . '/*Test.php') ?: [] as $file) {
    echo basename($file) . "\n";
    require $file;
}

$pass = $GLOBALS['__edi_pass'];
$fail = $GLOBALS['__edi_fail'];
echo "\n{$pass} passed, {$fail} failed\n";
exit($fail > 0 ? 1 : 0);
