<?php

declare(strict_types=1);

require_once __DIR__ . '/../custom/Espo/Custom/Services/FiscalProfileRateResolver.php';

use Espo\Custom\Services\FiscalProfileRateResolver;

$tests = [
    ['10.000', 10.0],
    ['22', 22.0],
    ['0.10', 10.0],
    [10, 10.0],
];

$ok = true;

foreach ($tests as [$in, $expected]) {
    $r = FiscalProfileRateResolver::parsePercentRate($in);

    if ($r === null || abs($r - $expected) > 0.001) {
        fwrite(STDERR, 'FAIL parsePercentRate(' . json_encode($in) . ') = '
            . var_export($r, true) . " expected {$expected}\n");
        $ok = false;
    }
}

fwrite(STDOUT, ($ok ? 'OK' : 'FAIL') . PHP_EOL);

exit($ok ? 0 : 1);
