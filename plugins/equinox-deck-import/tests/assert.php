<?php
// Minimal assertion harness (no phpunit). Tracks a global pass/fail tally.

$GLOBALS['__edi_pass'] = 0;
$GLOBALS['__edi_fail'] = 0;

function edi_check(bool $cond, string $msg): void
{
    if ($cond) {
        $GLOBALS['__edi_pass']++;
        echo "  ok   - {$msg}\n";
    } else {
        $GLOBALS['__edi_fail']++;
        echo "  FAIL - {$msg}\n";
    }
}

function assertSame($expected, $actual, string $msg): void
{
    edi_check(
        $expected === $actual,
        $msg . ($expected === $actual ? '' : ' (expected ' . var_export($expected, true) . ', got ' . var_export($actual, true) . ')')
    );
}

function assertTrue($value, string $msg): void
{
    edi_check($value === true, $msg);
}

function assertThrows(callable $fn, string $exceptionClass, string $msg): void
{
    try {
        $fn();
        edi_check(false, $msg . ' (no exception thrown)');
    } catch (\Throwable $e) {
        edi_check($e instanceof $exceptionClass, $msg . ' (got ' . get_class($e) . ')');
    }
}
