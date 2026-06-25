#!/usr/bin/env php
<?php
/**
 * COMBO 9+9: imponibile netto − prezzo codice netto (4.400 IVI → 4.000).
 */
$importoVendutoIvi = 4500.0;
$aliquota = 10.0;
$imponibileNet = round($importoVendutoIvi / (1 + $aliquota / 100), 2);
$prezzoCodiceIvi = 4400.0;
$prezzoCodiceNet = round($prezzoCodiceIvi / (1 + $aliquota / 100), 2);
$minusPlus = round($imponibileNet - $prezzoCodiceNet, 2);
$ok = abs($prezzoCodiceNet - 4000.0) < 0.02 && abs($minusPlus - 90.91) < 0.02;

fwrite(STDOUT, "codice IVI {$prezzoCodiceIvi} → netto {$prezzoCodiceNet}\n");
fwrite(STDOUT, "imponibile {$imponibileNet} − codice netto {$prezzoCodiceNet} = {$minusPlus} (≈91) → " . ($ok ? 'OK' : 'FAIL') . PHP_EOL);

exit($ok ? 0 : 1);
