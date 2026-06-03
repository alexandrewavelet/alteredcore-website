<?php
// Minimal PSR-4-style autoloader scoped to this plugin (no Composer, no libs).
// Maps  AlteredCore\EquinoxDeckImport\<Sub\Class>  ->  <plugin>/src/<Sub/Class>.php
// Required (require_once) by every entry point — pages/, papi/ — and by tests/.
// Only one entry point runs per request, so the autoloader registers once.

spl_autoload_register(function (string $class): void {
    $prefix = 'AlteredCore\\EquinoxDeckImport\\';
    $len = strlen($prefix);
    if (strncmp($class, $prefix, $len) !== 0) {
        return; // not ours — let other autoloaders handle it
    }
    $relative = substr($class, $len);
    $path = __DIR__ . '/../src/' . str_replace('\\', '/', $relative) . '.php';
    if (is_file($path)) {
        require_once $path;
    }
});
