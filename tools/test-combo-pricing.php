#!/usr/bin/env php
<?php
/**
 * COMBO 9+9: imponibile netto − prezzo codice netto (4.200 IVI → 3.818,18).
 */
$importoVendutoIvi = 4500.0;
$aliquota = 10.0;
$imponibileNet = round($importoVendutoIvi / (1 + $aliquota / 100), 2);
$prezzoCodiceIvi = 4200.0;
$prezzoCodiceNet = round($prezzoCodiceIvi / (1 + $aliquota / 100), 2);
$minusPlus = round($imponibileNet - $prezzoCodiceNet, 2);
$ok = abs($prezzoCodiceNet - 3818.18) < 0.02 && abs($minusPlus - 272.73) < 0.02;

fwrite(STDOUT, "codice IVI {$prezzoCodiceIvi} → netto {$prezzoCodiceNet}\n");
fwrite(STDOUT, "imponibile {$imponibileNet} − codice netto {$prezzoCodiceNet} = {$minusPlus} (≈272,73) → " . ($ok ? 'OK' : 'FAIL') . PHP_EOL);

exit($ok ? 0 : 1);
